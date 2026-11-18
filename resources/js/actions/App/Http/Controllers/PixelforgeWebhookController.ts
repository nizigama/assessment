import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PixelforgeWebhookController::__invoke
* @see app/Http/Controllers/PixelforgeWebhookController.php:13
* @route '/webhooks/pixelforge'
*/
const PixelforgeWebhookController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: PixelforgeWebhookController.url(options),
    method: 'post',
})

PixelforgeWebhookController.definition = {
    methods: ["post"],
    url: '/webhooks/pixelforge',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PixelforgeWebhookController::__invoke
* @see app/Http/Controllers/PixelforgeWebhookController.php:13
* @route '/webhooks/pixelforge'
*/
PixelforgeWebhookController.url = (options?: RouteQueryOptions) => {
    return PixelforgeWebhookController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PixelforgeWebhookController::__invoke
* @see app/Http/Controllers/PixelforgeWebhookController.php:13
* @route '/webhooks/pixelforge'
*/
PixelforgeWebhookController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: PixelforgeWebhookController.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\PixelforgeWebhookController::__invoke
* @see app/Http/Controllers/PixelforgeWebhookController.php:13
* @route '/webhooks/pixelforge'
*/
const PixelforgeWebhookControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: PixelforgeWebhookController.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\PixelforgeWebhookController::__invoke
* @see app/Http/Controllers/PixelforgeWebhookController.php:13
* @route '/webhooks/pixelforge'
*/
PixelforgeWebhookControllerForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: PixelforgeWebhookController.url(options),
    method: 'post',
})

PixelforgeWebhookController.form = PixelforgeWebhookControllerForm

export default PixelforgeWebhookController