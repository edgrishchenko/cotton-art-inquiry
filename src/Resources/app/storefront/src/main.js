import InquiryPlugin from './inquiry/inquiry.plugin';
import InquiryPersonalPlugin from './inquiry/inquiry-personal.plugin';
import InquiryFinishPlugin from './inquiry/inquiry-finish.plugin';
import InquiryCartWidgetPlugin from './plugin/header/inquiry-cart-widget.plugin';
import AddToInquiryCartPlugin from "./plugin/add-to-inquiry-cart/add-to-inquiry-cart.plugin";
import OffCanvasInquiryCartPlugin from "./plugin/offcanvas-inquiry-cart/offcanvas-inquiry-cart.plugin";

const PluginManager = window.PluginManager;
PluginManager.register('InquiryPlugin', InquiryPlugin, '[data-inquiry-plugin]');
PluginManager.register('InquiryPersonalPlugin', InquiryPersonalPlugin, '[data-inquiry-personal-plugin]');
PluginManager.register('InquiryFinishPlugin', InquiryFinishPlugin, '[data-inquiry-finish-plugin]');
PluginManager.register('InquiryCartWidget', InquiryCartWidgetPlugin, '[data-inquiry-cart-widget]');
PluginManager.register('OffCanvasInquiryCart', OffCanvasInquiryCartPlugin, '[data-off-canvas-inquiry-cart]');
PluginManager.register('AddToInquiryCart', AddToInquiryCartPlugin, '[data-add-to-inquiry-cart]');