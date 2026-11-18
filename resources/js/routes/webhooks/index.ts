import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\PixelforgeWebhookController::__invoke
* @see app/Http/Controllers/PixelforgeWebhookController.php:13
* @route '/webhooks/pixelforge'
*/
export const pixelforge = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pixelforge.url(options),
    method: 'post',
})

pixelforge.definition = {
    methods: ["post"],
    url: '/webhooks/pixelforge',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PixelforgeWebhookController::__invoke
* @see app/Http/Controllers/PixelforgeWebhookController.php:13
* @route '/webhooks/pixelforge'
*/
pixelforge.url = (options?: RouteQueryOptions) => {
    return pixelforge.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PixelforgeWebhookController::__invoke
* @see app/Http/Controllers/PixelforgeWebhookController.php:13
* @route '/webhooks/pixelforge'
*/
pixelforge.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pixelforge.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\PixelforgeWebhookController::__invoke
* @see app/Http/Controllers/PixelforgeWebhookController.php:13
* @route '/webhooks/pixelforge'
*/
const pixelforgeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: pixelforge.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\PixelforgeWebhookController::__invoke
* @see app/Http/Controllers/PixelforgeWebhookController.php:13
* @route '/webhooks/pixelforge'
*/
pixelforgeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: pixelforge.url(options),
    method: 'post',
})

pixelforge.form = pixelforgeForm

const webhooks = {
    pixelforge: Object.assign(pixelforge, pixelforge),
}

export default webhooks