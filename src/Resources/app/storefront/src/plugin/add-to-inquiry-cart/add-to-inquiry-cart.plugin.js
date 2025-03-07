import AddToCartPlugin from 'src/plugin/add-to-cart/add-to-cart.plugin'
import Iterator from 'src/helper/iterator.helper';

export default class AddToInquiryCartPlugin extends AddToCartPlugin {

    static options = {
        ...super.options,

        redirectTo: 'frontend.inquiry.cart.offcanvas',
    }
    /**
     *
     * @param {string} requestUrl
     * @param {{}|FormData} formData
     * @private
     */
    _openOffCanvasCarts(requestUrl, formData) {
        const offCanvasCartInstances = window.PluginManager.getPluginInstances('OffCanvasInquiryCart');
        Iterator.iterate(offCanvasCartInstances, instance => this._openOffCanvasCart(instance, requestUrl, formData));
    }
}
