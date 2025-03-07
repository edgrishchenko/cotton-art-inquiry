import OffCanvasCartPlugin from 'src/plugin/offcanvas-cart/offcanvas-cart.plugin';
import Iterator from 'src/helper/iterator.helper';

export default class OffCanvasInquiryCartPlugin extends OffCanvasCartPlugin {
    _onChangeShippingMethod(event) {
        event.preventDefault();

        this.$emitter.publish('onShippingMethodChange');
        const url = window.router['frontend.inquiry.cart.offcanvas'];

        const _callback = () => {
            this.client.get(url, response => {
                this._updateOffCanvasContent(response);
                this._registerEvents();
            }, 'text/html');
        };

        this._fireRequest(event.target.form, '.offcanvas-summary', _callback);
    }

    _onOpenOffCanvasCart(event) {
        event.preventDefault();

        this.openOffCanvas(window.router['frontend.inquiry.cart.offcanvas'], false);
    }

    /**
     * Update all registered cart widgets
     *
     * @private
     */
    _fetchCartWidgets() {
        const CartWidgetPluginInstances = window.PluginManager.getPluginInstances('InquiryCartWidget');
        Iterator.iterate(CartWidgetPluginInstances, instance => instance.fetch());

        this.$emitter.publish('fetchCartWidgets');
    }
}
