import Plugin from 'src/plugin-system/plugin.class';

export default class InquiryFinishPlugin extends Plugin {
    init() {
        sessionStorage.clear();
    }
}