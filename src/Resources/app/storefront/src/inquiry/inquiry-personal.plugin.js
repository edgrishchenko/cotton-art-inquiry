import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import HttpClient from 'src/service/http-client.service';

export default class InquiryPersonalPlugin extends Plugin {
    init() {
        this.client = new HttpClient();

        this._registerEvents();
    }

    _registerEvents() {
        const inputs = DomAccess.querySelectorAll(this.el, 'input, select');
        inputs.forEach((input) => {
            if (sessionStorage.getItem(input.name)) {
                input.value = sessionStorage.getItem(input.name);
                input.dispatchEvent(new Event('change'));
            }
            input.addEventListener('change', () => {
                sessionStorage.setItem(input.name, input.value)
            });
        });
    }
}

