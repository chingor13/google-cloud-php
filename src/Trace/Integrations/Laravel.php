<?php
/**
 * Copyright 2017 Google Inc. All Rights Reserved.
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

namespace Google\Cloud\Trace\Integrations;

class Laravel
{
    public static function load()
    {
        if (!extension_loaded('stackdriver_trace')) {
            return;
        }

        stackdriver_trace_method('Illuminate\Database\Eloquent\Builder', 'getModels', function($scope, $columns) {
            return [
                'name' => 'eloquent/get',
                'labels' => [
                    'model' => get_class($scope->model);
                ]
            ];
        });
        stackdriver_trace_method('Illuminate\Database\Eloquent\Model', 'performInsert');
        stackdriver_trace_method('Illuminate\Database\Eloquent\Model', 'performUpdate');
        stackdriver_trace_method('Illuminate\Database\Eloquent\Model', 'delete');
        stackdriver_trace_method('Illuminate\Database\Eloquent\Model', 'destroy');
        stackdriver_trace_method('Illuminate\View\Engines\CompilerEngine', 'get', function ($scope, $path, $data) {
            return [
                'name' => 'laravel/view',
                'labels' => [
                    'path' => $path
                ]
            ];
        });
    }
}
