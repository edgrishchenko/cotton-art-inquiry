import QuantitySelectorPlugin from 'src/plugin/quantity-selector/quantity-selector.plugin';

export default class InquiryQuantitySelectorPlugin extends QuantitySelectorPlugin {
    _triggerChange(btn) {
        super._triggerChange(btn);

        this._inquiryQuantity = document.querySelector('[data-quantity-inquiry]');

        if (this._inquiryQuantity) {
            this._inquiryQuantity.value = this._input.value;
        }
    }
}