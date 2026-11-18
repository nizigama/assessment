import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
const UsageReportController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: UsageReportController.url(options),
    method: 'get',
})

UsageReportController.definition = {
    methods: ["get","head"],
    url: '/admin/usage',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
UsageReportController.url = (options?: RouteQueryOptions) => {
    return UsageReportController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
UsageReportController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: UsageReportController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
UsageReportController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: UsageReportController.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
const UsageReportControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: UsageReportController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
UsageReportControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: UsageReportController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
UsageReportControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: UsageReportController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

UsageReportController.form = UsageReportControllerForm

export default UsageReportController