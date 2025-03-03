import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

export default class InquiryPersonalPlugin extends Plugin {
    init() {
        window.addEventListener('load', this._registerEvents.bind(this));
    }

    _registerEvents() {
        const inputs = DomAccess.querySelectorAll(this.el, 'input, select');
        inputs.forEach((input) => {
            if (sessionStorage.getItem(input.id)) {
                input.value = sessionStorage.getItem(input.id);
                input.dispatchEvent(new Event('input'));
            }
            input.addEventListener('input', () => {
                sessionStorage.setItem(input.id, input.value)
            });
        });
    }
}

