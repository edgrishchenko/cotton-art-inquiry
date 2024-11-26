import InquiryPlugin from './inquiry/inquiry.plugin';
import InquiryPersonalPlugin from './inquiry/inquiry-personal.plugin';
import InquiryFinishPlugin from './inquiry/inquiry-finish.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('InquiryPlugin', InquiryPlugin, '[data-inquiry-plugin]');
PluginManager.register('InquiryPersonalPlugin', InquiryPersonalPlugin, '[data-inquiry-personal-plugin]');
PluginManager.register('InquiryFinishPlugin', InquiryFinishPlugin, '[data-inquiry-finish-plugin]');