import CartWidgetPlugin from 'src/plugin/header/cart-widget.plugin';
import Storage from 'src/helper/storage/storage.helper';

export default class InquiryCartWidgetPlugin extends CartWidgetPlugin {
    static options = {
        ...super.options,

        cartWidgetStorageKey: 'inquiry-cart-widget-template',
        emptyCartWidgetStorageKey: 'empty-inquiry-cart-widget',
    };

    /**
     * Fetch the current cart widget template by calling the api
     * and persist the response to the browser's session storage
     */
    fetch() {
        this._client.get(window.router['frontend.inquiry.info'], (content, response) => {
            if (response.status >= 500) {
                return;
            }

            if (response.status === 204) {
                Storage.removeItem(this.options.cartWidgetStorageKey);
                const emptyCartWidget = Storage.getItem(this.options.emptyCartWidgetStorageKey);
                if (emptyCartWidget) {
                    this.el.innerHTML = emptyCartWidget;
                }

                return;
            }

            Storage.setItem(this.options.cartWidgetStorageKey, content);
            if (content.length) {
                this.el.innerHTML = content;
            }

            this.$emitter.publish('fetch', { content });
        });
    }
}
