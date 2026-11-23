import Controller from '@ember/controller';
import { inject as service } from '@ember/service';

export default class InstallController extends Controller {
    @service installation;

    get isRefreshing() {
        return this.installation.isRefreshing;
    }
}
