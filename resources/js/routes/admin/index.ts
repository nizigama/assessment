import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
export const usage = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: usage.url(options),
    method: 'get',
})

usage.definition = {
    methods: ["get","head"],
    url: '/admin/usage',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
usage.url = (options?: RouteQueryOptions) => {
    return usage.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
usage.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: usage.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
usage.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: usage.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
const usageForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: usage.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
usageForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: usage.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\UsageReportController::__invoke
* @see app/Http/Controllers/Admin/UsageReportController.php:24
* @route '/admin/usage'
*/
usageForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: usage.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

usage.form = usageForm

const admin = {
    usage: Object.assign(usage, usage),
}

export default admin