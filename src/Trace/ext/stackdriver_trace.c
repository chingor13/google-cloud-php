/*
 * Copyright 2017 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

#include "php_stackdriver_trace.h"
#include <sys/time.h>
#include "stackdriver_trace.h"
#include "Zend/zend_compile.h"
#include "Zend/zend_closures.h"
#include "zend_extensions.h"

// True global for storing the original zend_execute_ex function pointer
void (*original_zend_execute_ex) (zend_execute_data *execute_data TSRMLS_DC);

// Type for a trace callback handler
typedef void (*stackdriver_trace_callback)(stackdriver_trace_span_t *span, zend_execute_data *data TSRMLS_DC);

ZEND_DECLARE_MODULE_GLOBALS(stackdriver_trace)

// List of functions provided by this extension
static zend_function_entry stackdriver_trace_functions[] = {
    PHP_FE(stackdriver_trace_version, NULL)
    PHP_FE(stackdriver_trace_function, NULL)
    PHP_FE(stackdriver_trace_method, NULL)
    PHP_FE(stackdriver_trace_list, NULL)
    PHP_FE(stackdriver_trace_begin, NULL)
    PHP_FE(stackdriver_trace_finish, NULL)
    PHP_FE(stackdriver_trace_clear, NULL)
    PHP_FE(stackdriver_trace_set_context, NULL)
    PHP_FE(stackdriver_trace_context, NULL)
    PHP_FE_END
};

// Registers the lifecycle hooks for this extension
zend_module_entry stackdriver_trace_module_entry = {
    STANDARD_MODULE_HEADER,
    PHP_STACKDRIVER_TRACE_EXTNAME,
    stackdriver_trace_functions,
    PHP_MINIT(stackdriver_trace),
    PHP_MSHUTDOWN(stackdriver_trace),
    PHP_RINIT(stackdriver_trace),
    PHP_RSHUTDOWN(stackdriver_trace),
    NULL, // name of the MINFO function or NULL if not applicable
    PHP_STACKDRIVER_TRACE_VERSION,
    STANDARD_MODULE_PROPERTIES
};

ZEND_GET_MODULE(stackdriver_trace)

// returns the version of the stackdriver extension
PHP_FUNCTION(stackdriver_trace_version)
{
    RETURN_STRING(PHP_STACKDRIVER_TRACE_VERSION);
}

static void php_stackdriver_trace_globals_ctor(void *pDest TSRMLS_DC)
{
    zend_stackdriver_trace_globals *stackdriver_trace_global = (zend_stackdriver_trace_globals *) pDest;
}

// Returns the callback handler for the specified function name.
// This is always a zval* and should be either an array or a pointer to a callback function.
static zval *stackdriver_trace_find_callback(zend_string *function_name)
{
    return zend_hash_find(STACKDRIVER_TRACE_G(user_traced_functions), function_name);
}

// Add a label to the trace span struct
static int stackdriver_trace_add_label(stackdriver_trace_span_t *span, zend_string *k, zend_string *v)
{
    zval zv;

    // instantiate labels if not already created
    if (span->labels == NULL) {
        span->labels = emalloc(sizeof(HashTable));
        zend_hash_init(span->labels, 4, NULL, ZVAL_PTR_DTOR, 0);
    }

    // put the string value into a zval and save it in the HashTable
    ZVAL_STRING(&zv, ZSTR_VAL(v));

    if (zend_hash_update(span->labels, zend_string_copy(k), &zv) == NULL) {
        return FAILURE;
    } else {
        return SUCCESS;
    }
}

// Add a single label to the provided trace span struct
static int stackdriver_trace_add_label_str(stackdriver_trace_span_t *span, char *k, zend_string *v)
{
    return stackdriver_trace_add_label(span, zend_string_init(k, strlen(k), 0), v);
}

// Merge labels from the provided zval array into the provided trace span struct
static int stackdriver_trace_add_labels_merge(stackdriver_trace_span_t *span, zval *label_array)
{
    ulong idx;
    zend_string *k;
    zval *v;
    HashTable *ht = Z_ARRVAL_P(label_array);
    // instantiate labels if not already created
    if (span->labels == NULL) {
        span->labels = emalloc(sizeof(HashTable));
        zend_hash_init(span->labels, 4, NULL, ZVAL_PTR_DTOR, 0);
    }

    zend_hash_merge(span->labels, ht, zval_add_ref, 0);
    return SUCCESS;
}

// Return the current timestamp as a double
static double stackdriver_trace_now()
{
    struct timeval tv;
    gettimeofday(&tv, NULL);

    return (double) (tv.tv_sec + tv.tv_usec / 1000000.00);
}

// Update the provided span with the provided zval (array) of span options
static void stackdriver_trace_modify_span_with_array(stackdriver_trace_span_t *span, zval *span_options)
{
    HashTable *ht;
    ulong idx;
    zend_string *k;
    zval *v;
    ht = Z_ARR_P(span_options);

    ZEND_HASH_FOREACH_KEY_VAL(ht, idx, k, v) {
        if (strcmp(ZSTR_VAL(k), "labels") == 0) {
            stackdriver_trace_add_labels_merge(span, v);
        } else if (strcmp(ZSTR_VAL(k), "startTime") == 0) {
            span->start = Z_DVAL_P(v);
        } else if (strcmp(ZSTR_VAL(k), "name") == 0) {
            span->name = zend_string_copy(Z_STR_P(v));
        }
    } ZEND_HASH_FOREACH_END();
}

// Call the provided Closure with the provided parameters to the traced function
static int stackdriver_trace_zend_fcall_closure(zend_execute_data *execute_data, stackdriver_trace_span_t *span, zval *closure TSRMLS_DC)
{
    int i, num_args = EX_NUM_ARGS(), has_scope = 0;
    zend_fcall_info fci;
    zend_fcall_info_cache fcc;
    zval closure_result;
    zval args[num_args + 1];

    if (getThis() == NULL) {
        ZVAL_NULL(&args[0]);
    } else {
        has_scope = 1;
        ZVAL_ZVAL(&args[0], getThis(), 0, 1);
    }

    for (i = 0; i < num_args; i++) {
        ZVAL_ZVAL(&args[i + has_scope], EX_VAR_NUM(i), 0, 1);
    }

    if (zend_fcall_info_init(
            closure,
            0,
            &fci,
            &fcc,
            NULL,
            NULL
            TSRMLS_CC
        ) != SUCCESS) {
        return FAILURE;
    };

    fci.retval = &closure_result;
    fci.params = &args[0];
    fci.param_count = num_args + has_scope;

    fcc.initialized = 1;

    if (zend_call_function(&fci, &fcc TSRMLS_CC) != SUCCESS) {
          return FAILURE;
    }

    stackdriver_trace_modify_span_with_array(span, &closure_result);

    return SUCCESS;
}

// Handle the callback for the traced method depending on the type
//   - if the zval is a pointer, then it's a pointer to a c-function callback
//   - if the zval is an array, then assume it's the trace span initialization options
//   - if the zval is a Closure, then execute the closure and take the results as
//     the trace span initialization options
static void stackdriver_trace_execute_callback(stackdriver_trace_span_t *span, zend_execute_data *execute_data, zval *span_options TSRMLS_DC)
{
    stackdriver_trace_callback cb;

    if (Z_TYPE_P(span_options) == IS_PTR) {
        cb = (stackdriver_trace_callback)Z_PTR_P(span_options);
        cb(span, execute_data TSRMLS_CC);
    } else if (Z_TYPE_P(span_options) == IS_ARRAY) {
        stackdriver_trace_modify_span_with_array(span, span_options);
    } else if (Z_TYPE_P(span_options) == IS_OBJECT) {
        if (Z_OBJCE_P(span_options) == zend_ce_closure) {
            stackdriver_trace_zend_fcall_closure(execute_data, span, span_options TSRMLS_CC);
        }
    }
}

// Start a new trace span. Inherit the parent span id from the curernt trace context.
static stackdriver_trace_span_t *stackdriver_trace_begin(zend_string *function_name, zend_execute_data *execute_data TSRMLS_DC)
{
    stackdriver_trace_span_t *span = emalloc(sizeof(stackdriver_trace_span_t));

    span->start = stackdriver_trace_now();
    span->name = zend_string_copy(function_name);
    span->span_id = php_mt_rand();
    span->labels = NULL;

    if (STACKDRIVER_TRACE_G(current_span)) {
        span->parent = STACKDRIVER_TRACE_G(current_span);
    } else {
        span->parent = NULL;
    }
    STACKDRIVER_TRACE_G(current_span) = span;
    STACKDRIVER_TRACE_G(spans)[STACKDRIVER_TRACE_G(span_count)++] = span;

    return span;
}

// Finish the current trace span. Set the new current trace span to this span's parent if there is one.
static int stackdriver_trace_finish()
{
    stackdriver_trace_span_t *span = STACKDRIVER_TRACE_G(current_span);

    if (!span) {
        return FAILURE;
    }

    // set current time for now
    span->stop = stackdriver_trace_now();

    STACKDRIVER_TRACE_G(current_span) = span->parent;

    return SUCCESS;
}

// Given a class name and a function name, return a new string that represents the function name
static zend_string *stackdriver_trace_generate_class_name(zend_string *class_name, zend_string *function_name)
{
    int len = class_name->len + function_name->len + 2;
    zend_string *result = zend_string_alloc(len, 0);

    strcpy(ZSTR_VAL(result), class_name->val);
    strcat(ZSTR_VAL(result), "::");
    strcat(ZSTR_VAL(result), function_name->val);
    return result;
}

// Given the current execution data, determine the name of the function that is being called.
static zend_string *stackdriver_trace_get_current_function_name()
{
    zend_execute_data *data;
    zend_string *result, *function_name;
    zend_function *current_function;

    data = EG(current_execute_data);

    // We don't have any current execution data -> don't care to trace
    if (!data) {
        return NULL;
    }

    // Fetch the function name from the current execution state
    current_function = data->func;
    function_name = current_function->common.function_name;

    // This is a special directive like 'require' -> don't care to trace
    if (!function_name) {
        return NULL;
    }

    // Fetch the current class if any
    if (current_function->common.scope) {
        result = stackdriver_trace_generate_class_name(current_function->common.scope->name, function_name);
    } else {
        result = zend_string_copy(function_name);
    }

    return result;
}

/* {{{ proto bool stackdriver_trace_begin($function_name, $handler)
Start a new trace span */
PHP_FUNCTION(stackdriver_trace_begin)
{
    zend_string *function_name;
    zval *span_options;
    stackdriver_trace_span_t *span;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "Sa", &function_name, &span_options) == FAILURE) {
        RETURN_FALSE;
    }

    span = stackdriver_trace_begin(function_name, execute_data TSRMLS_CC);
    stackdriver_trace_execute_callback(span, execute_data, span_options TSRMLS_CC);
    RETURN_TRUE;
}

/* {{{ proto bool stackdriver_trace_finish()
Finish the current trace span */
PHP_FUNCTION(stackdriver_trace_finish)
{
    if (stackdriver_trace_finish() == SUCCESS) {
        RETURN_TRUE;
    }
    RETURN_FALSE;
}

// Reset the list of spans and free any allocated memory used
static void stackdriver_trace_clear(TSRMLS_D)
{
    int i;
    stackdriver_trace_span_t *span;
    for (i = 0; i < STACKDRIVER_TRACE_G(span_count); i++) {
        span = STACKDRIVER_TRACE_G(spans)[i];
        if (span->labels) {
            efree(span->labels);
        }
        efree(span);
    }
    STACKDRIVER_TRACE_G(span_count) = 0;
    STACKDRIVER_TRACE_G(current_span) = NULL;
    STACKDRIVER_TRACE_G(trace_id) = NULL;
    STACKDRIVER_TRACE_G(trace_parent_span_id) = 0;
}

/* {{{ proto int stackdriver_trace_clear()
Reset the list of spans */
PHP_FUNCTION(stackdriver_trace_clear)
{
    stackdriver_trace_clear(TSRMLS_C);
    RETURN_TRUE;
}

/* {{{ proto int stackdriver_trace_set_context($traceId, $parentSpanId = null)
Set the trace context */
PHP_FUNCTION(stackdriver_trace_set_context)
{
    zend_string *trace_id;
    long parent_span_id;
    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "S|L", &trace_id, &parent_span_id) == FAILURE) {
        RETURN_FALSE;
    }

    STACKDRIVER_TRACE_G(trace_id) = zend_string_copy(trace_id);
    STACKDRIVER_TRACE_G(trace_parent_span_id) = parent_span_id;
}

/* {{{ proto int stackdriver_trace_context()
Return the current trace context. Returns a Stackdriver\Trace\Context instance */
PHP_FUNCTION(stackdriver_trace_context)
{
    stackdriver_trace_span_t *span = STACKDRIVER_TRACE_G(current_span);
    object_init_ex(return_value, stackdriver_trace_context_ce);

    if (span) {
        zend_update_property_long(stackdriver_trace_context_ce, return_value, "spanId", sizeof("spanId") - 1, span->span_id);
    }
    if (STACKDRIVER_TRACE_G(trace_id)) {
        zend_update_property_str(stackdriver_trace_context_ce, return_value, "traceId", sizeof("traceId") - 1, STACKDRIVER_TRACE_G(trace_id));
    }
}

/**
 * This method replaces the internal zend_execute_ex method used to dispatch calls
 * to user space code. The original zend_execute_ex method is moved to
 * STACKDRIVER_TRACE_G(_zend_execute_ex)
 */
void stackdriver_trace_execute_ex (zend_execute_data *execute_data TSRMLS_DC) {
    zend_string *function_name = stackdriver_trace_get_current_function_name();
    zval *trace_handler;
    stackdriver_trace_span_t *span;

    if (function_name) {
        trace_handler = stackdriver_trace_find_callback(function_name);

        if (trace_handler != NULL) {
            span = stackdriver_trace_begin(function_name, execute_data TSRMLS_CC);
            original_zend_execute_ex(execute_data TSRMLS_CC);
            stackdriver_trace_execute_callback(span, execute_data, trace_handler TSRMLS_CC);
            stackdriver_trace_finish();
        } else {
            original_zend_execute_ex(execute_data TSRMLS_CC);
        }
    } else {
        original_zend_execute_ex(execute_data TSRMLS_CC);
    }
}

/* {{{ proto int stackdriver_trace_function($function_name, $handler)
Trace a function call */
PHP_FUNCTION(stackdriver_trace_function)
{
    zend_string *function_name;
    zval *handler, *copy;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "S|z", &function_name, &handler) == FAILURE) {
        RETURN_FALSE;
    }

    if (handler == NULL) {
        zval h;
        ZVAL_LONG(&h, 1);
        handler = &h;
    }

    // TODO: clean this up at the end of the request
    PHP_STACKDRIVER_MAKE_STD_ZVAL(copy);
    ZVAL_ZVAL(copy, handler, 1, 0);

    zend_hash_update(STACKDRIVER_TRACE_G(user_traced_functions), function_name, copy);
    RETURN_TRUE;
}

/* {{{ proto int stackdriver_trace_method($class_name, $function_name, $handler)
Trace a class method or instance method */
PHP_FUNCTION(stackdriver_trace_method)
{
    zend_function *fe;
    zend_string *class_name, *function_name, *key;
    zval *handler, *copy;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "SS|z", &class_name, &function_name, &handler) == FAILURE) {
        RETURN_FALSE;
    }

    if (handler == NULL) {
        zval h;
        ZVAL_LONG(&h, 1);
        handler = &h;
    }

    // TODO: clean this up at the end of the request
    PHP_STACKDRIVER_MAKE_STD_ZVAL(copy);
    ZVAL_ZVAL(copy, handler, 1, 0);

    key = stackdriver_trace_generate_class_name(class_name, function_name);
    zend_hash_update(STACKDRIVER_TRACE_G(user_traced_functions), key, handler);

    RETURN_FALSE;
}

// Given a HashTable of labels, write the values into the provided pointer of the label_array
static int stackdriver_labels_to_zval_array(HashTable *ht, zval *label_array)
{
    ulong idx;
    zend_string *k;
    zval *v;
    HashTable *label_ht;

    array_init(label_array);
    label_ht = Z_ARRVAL_P(label_array);

    ZEND_HASH_FOREACH_KEY_VAL(ht, idx, k, v) {
        if (add_assoc_str(label_array, ZSTR_VAL(k), Z_STR_P(v)) != SUCCESS) {
            php_prinf("failed to add_assoc_zval\n");
            return FAILURE;
        }

    } ZEND_HASH_FOREACH_END();

    return SUCCESS;
}

/* {{{ proto int stackdriver_trace_list()
Returns an array of Stackdriver\Trace\Span instances that have been collected for this request */
PHP_FUNCTION(stackdriver_trace_list)
{
    int i;
    stackdriver_trace_span_t *trace_span;
    int num_spans = STACKDRIVER_TRACE_G(span_count);
    zval labels[num_spans], spans[num_spans];

    // Set up return value to be an array of size num_spans
    array_init(return_value);

    for (i = 0; i < num_spans; i++) {
        object_init_ex(&spans[i], stackdriver_trace_span_ce);

        trace_span = STACKDRIVER_TRACE_G(spans)[i];
        zend_update_property_long(stackdriver_trace_span_ce, &spans[i], "spanId", sizeof("spanId") - 1, trace_span->span_id);
        if (trace_span->parent) {
            zend_update_property_long(stackdriver_trace_span_ce, &spans[i], "parentSpanId", sizeof("parentSpanId") - 1, trace_span->parent->span_id);
        } else if (STACKDRIVER_TRACE_G(trace_parent_span_id)) {
            zend_update_property_long(stackdriver_trace_span_ce, &spans[i], "parentSpanId", sizeof("parentSpanId") - 1, STACKDRIVER_TRACE_G(trace_parent_span_id));
        }
        zend_update_property_str(stackdriver_trace_span_ce, &spans[i], "name", sizeof("name") - 1, trace_span->name);
        zend_update_property_double(stackdriver_trace_span_ce, &spans[i], "startTime", sizeof("startTime") - 1, trace_span->start);
        zend_update_property_double(stackdriver_trace_span_ce, &spans[i], "endTime", sizeof("endTime") - 1, trace_span->stop);

        array_init(&labels[i]);
        if (trace_span->labels) {
            stackdriver_labels_to_zval_array(trace_span->labels, &labels[i]);
        }
        zend_update_property(stackdriver_trace_span_ce, &spans[i], "labels", sizeof("labels") - 1, &labels[i]);

        add_next_index_zval(return_value, &spans[i]);
    }
}

/* {{{ PHP_MINIT_FUNCTION
 */
PHP_MINIT_FUNCTION(stackdriver_trace)
{
    // allocate global request variables
#ifdef ZTS
    ts_allocate_id(&stackdriver_trace_globals_id, sizeof(zend_stackdriver_trace_globals), php_stackdriver_trace_globals_ctor, NULL);
#else
    php_stackdriver_trace_globals_ctor(&php_stackdriver_trace_globals_ctor);
#endif

    // Save original zend execute functions and use our own to instrument function calls
    original_zend_execute_ex = zend_execute_ex;
    zend_execute_ex = stackdriver_trace_execute_ex;

    stackdriver_trace_span_minit(INIT_FUNC_ARGS_PASSTHRU);
    stackdriver_trace_context_minit(INIT_FUNC_ARGS_PASSTHRU);

    return SUCCESS;
}
/* }}} */

/* {{{ PHP_MSHUTDOWN_FUNCTION
 */
PHP_MSHUTDOWN_FUNCTION(stackdriver_trace)
{
    // Put the original zend execute function back.
    zend_execute_ex = original_zend_execute_ex;

    return SUCCESS;
}
/* }}} */

PHP_RINIT_FUNCTION(stackdriver_trace)
{
    // initialize storage for user traced functions - per request basis
    ALLOC_HASHTABLE(STACKDRIVER_TRACE_G(user_traced_functions));
    zend_hash_init(STACKDRIVER_TRACE_G(user_traced_functions), 16, NULL, ZVAL_PTR_DTOR, 0);

    STACKDRIVER_TRACE_G(current_span) = NULL;
    STACKDRIVER_TRACE_G(spans) = emalloc(64 * sizeof(stackdriver_trace_span_t *));
    STACKDRIVER_TRACE_G(span_count) = 0;
    STACKDRIVER_TRACE_G(trace_id) = NULL;
    STACKDRIVER_TRACE_G(trace_parent_span_id) = 0;

    return SUCCESS;
}

/* {{{ PHP_RSHUTDOWN_FUNCTION
 */
PHP_RSHUTDOWN_FUNCTION(stackdriver_trace)
{
    stackdriver_trace_clear(TSRMLS_C);
    efree(STACKDRIVER_TRACE_G(spans));

    return SUCCESS;
}
/* }}} */
