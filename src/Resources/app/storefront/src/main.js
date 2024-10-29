import InquiryPlugin from './inquiry/inquiry.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('InquiryPlugin', InquiryPlugin, '[data-inquiry-plugin]');