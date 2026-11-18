import GenerationController from './GenerationController'
import CreditController from './CreditController'
import Admin from './Admin'
import PixelforgeWebhookController from './PixelforgeWebhookController'
import Settings from './Settings'

const Controllers = {
    GenerationController: Object.assign(GenerationController, GenerationController),
    CreditController: Object.assign(CreditController, CreditController),
    Admin: Object.assign(Admin, Admin),
    PixelforgeWebhookController: Object.assign(PixelforgeWebhookController, PixelforgeWebhookController),
    Settings: Object.assign(Settings, Settings),
}

export default Controllers