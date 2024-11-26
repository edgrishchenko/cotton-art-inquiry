(()=>{"use strict";var e={857:e=>{var t=function(e){var t;return!!e&&"object"==typeof e&&"[object RegExp]"!==(t=Object.prototype.toString.call(e))&&"[object Date]"!==t&&e.$$typeof!==r},r="function"==typeof Symbol&&Symbol.for?Symbol.for("react.element"):60103;function i(e,t){return!1!==t.clone&&t.isMergeableObject(e)?a(Array.isArray(e)?[]:{},e,t):e}function n(e,t,r){return e.concat(t).map(function(e){return i(e,r)})}function s(e){return Object.keys(e).concat(Object.getOwnPropertySymbols?Object.getOwnPropertySymbols(e).filter(function(t){return Object.propertyIsEnumerable.call(e,t)}):[])}function o(e,t){try{return t in e}catch(e){return!1}}function a(e,r,l){(l=l||{}).arrayMerge=l.arrayMerge||n,l.isMergeableObject=l.isMergeableObject||t,l.cloneUnlessOtherwiseSpecified=i;var c,d,u=Array.isArray(r);return u!==Array.isArray(e)?i(r,l):u?l.arrayMerge(e,r,l):(d={},(c=l).isMergeableObject(e)&&s(e).forEach(function(t){d[t]=i(e[t],c)}),s(r).forEach(function(t){(!o(e,t)||Object.hasOwnProperty.call(e,t)&&Object.propertyIsEnumerable.call(e,t))&&(o(e,t)&&c.isMergeableObject(r[t])?d[t]=(function(e,t){if(!t.customMerge)return a;var r=t.customMerge(e);return"function"==typeof r?r:a})(t,c)(e[t],r[t],c):d[t]=i(r[t],c))}),d)}a.all=function(e,t){if(!Array.isArray(e))throw Error("first argument should be an array");return e.reduce(function(e,r){return a(e,r,t)},{})},e.exports=a}},t={};function r(i){var n=t[i];if(void 0!==n)return n.exports;var s=t[i]={exports:{}};return e[i](s,s.exports,r),s.exports}(()=>{r.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return r.d(t,{a:t}),t}})(),(()=>{r.d=(e,t)=>{for(var i in t)r.o(t,i)&&!r.o(e,i)&&Object.defineProperty(e,i,{enumerable:!0,get:t[i]})}})(),(()=>{r.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t)})(),(()=>{var e=r(857),t=r.n(e);class i{static ucFirst(e){return e.charAt(0).toUpperCase()+e.slice(1)}static lcFirst(e){return e.charAt(0).toLowerCase()+e.slice(1)}static toDashCase(e){return e.replace(/([A-Z])/g,"-$1").replace(/^-/,"").toLowerCase()}static toLowerCamelCase(e,t){let r=i.toUpperCamelCase(e,t);return i.lcFirst(r)}static toUpperCamelCase(e,t){return t?e.split(t).map(e=>i.ucFirst(e.toLowerCase())).join(""):i.ucFirst(e.toLowerCase())}static parsePrimitive(e){try{return/^\d+(.|,)\d+$/.test(e)&&(e=e.replace(",",".")),JSON.parse(e)}catch(t){return e.toString()}}}class n{static isNode(e){return"object"==typeof e&&null!==e&&(e===document||e===window||e instanceof Node)}static hasAttribute(e,t){if(!n.isNode(e))throw Error("The element must be a valid HTML Node!");return"function"==typeof e.hasAttribute&&e.hasAttribute(t)}static getAttribute(e,t){let r=!(arguments.length>2)||void 0===arguments[2]||arguments[2];if(r&&!1===n.hasAttribute(e,t))throw Error('The required property "'.concat(t,'" does not exist!'));if("function"!=typeof e.getAttribute){if(r)throw Error("This node doesn't support the getAttribute function!");return}return e.getAttribute(t)}static getDataAttribute(e,t){let r=!(arguments.length>2)||void 0===arguments[2]||arguments[2],s=t.replace(/^data(|-)/,""),o=i.toLowerCamelCase(s,"-");if(!n.isNode(e)){if(r)throw Error("The passed node is not a valid HTML Node!");return}if(void 0===e.dataset){if(r)throw Error("This node doesn't support the dataset attribute!");return}let a=e.dataset[o];if(void 0===a){if(r)throw Error('The required data attribute "'.concat(t,'" does not exist on ').concat(e,"!"));return a}return i.parsePrimitive(a)}static querySelector(e,t){let r=!(arguments.length>2)||void 0===arguments[2]||arguments[2];if(r&&!n.isNode(e))throw Error("The parent node is not a valid HTML Node!");let i=e.querySelector(t)||!1;if(r&&!1===i)throw Error('The required element "'.concat(t,'" does not exist in parent node!'));return i}static querySelectorAll(e,t){let r=!(arguments.length>2)||void 0===arguments[2]||arguments[2];if(r&&!n.isNode(e))throw Error("The parent node is not a valid HTML Node!");let i=e.querySelectorAll(t);if(0===i.length&&(i=!1),r&&!1===i)throw Error('At least one item of "'.concat(t,'" must exist in parent node!'));return i}static getFocusableElements(){let e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:document.body;return e.querySelectorAll('\n            input:not([tabindex^="-"]):not([disabled]):not([type="hidden"]),\n            select:not([tabindex^="-"]):not([disabled]),\n            textarea:not([tabindex^="-"]):not([disabled]),\n            button:not([tabindex^="-"]):not([disabled]),\n            a[href]:not([tabindex^="-"]):not([disabled]),\n            [tabindex]:not([tabindex^="-"]):not([disabled])\n        ')}static getFirstFocusableElement(){let e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:document.body;return this.getFocusableElements(e)[0]}static getLastFocusableElement(){let e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:document,t=this.getFocusableElements(e);return t[t.length-1]}}class s{publish(e){let t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},r=arguments.length>2&&void 0!==arguments[2]&&arguments[2],i=new CustomEvent(e,{detail:t,cancelable:r});return this.el.dispatchEvent(i),i}subscribe(e,t){let r=arguments.length>2&&void 0!==arguments[2]?arguments[2]:{},i=this,n=e.split("."),s=r.scope?t.bind(r.scope):t;if(r.once&&!0===r.once){let t=s;s=function(r){i.unsubscribe(e),t(r)}}return this.el.addEventListener(n[0],s),this.listeners.push({splitEventName:n,opts:r,cb:s}),!0}unsubscribe(e){let t=e.split(".");return this.listeners=this.listeners.reduce((e,r)=>([...r.splitEventName].sort().toString()===t.sort().toString()?this.el.removeEventListener(r.splitEventName[0],r.cb):e.push(r),e),[]),!0}reset(){return this.listeners.forEach(e=>{this.el.removeEventListener(e.splitEventName[0],e.cb)}),this.listeners=[],!0}get el(){return this._el}set el(e){this._el=e}get listeners(){return this._listeners}set listeners(e){this._listeners=e}constructor(e=document){this._el=e,e.$emitter=this,this._listeners=[]}}class o{init(){throw Error('The "init" method for the plugin "'.concat(this._pluginName,'" is not defined.'))}update(){}_init(){this._initialized||(this.init(),this._initialized=!0)}_update(){this._initialized&&this.update()}_mergeOptions(e){let r=i.toDashCase(this._pluginName),s=n.getDataAttribute(this.el,"data-".concat(r,"-config"),!1),o=n.getAttribute(this.el,"data-".concat(r,"-options"),!1),a=[this.constructor.options,this.options,e];s&&a.push(window.PluginConfigManager.get(this._pluginName,s));try{o&&a.push(JSON.parse(o))}catch(e){throw console.error(this.el),Error('The data attribute "data-'.concat(r,'-options" could not be parsed to json: ').concat(e.message))}return t().all(a.filter(e=>e instanceof Object&&!(e instanceof Array)).map(e=>e||{}))}_registerInstance(){window.PluginManager.getPluginInstancesFromElement(this.el).set(this._pluginName,this),window.PluginManager.getPlugin(this._pluginName,!1).get("instances").push(this)}_getPluginName(e){return e||(e=this.constructor.name),e}constructor(e,t={},r=!1){if(!n.isNode(e))throw Error("There is no valid element given.");this.el=e,this.$emitter=new s(this.el),this._pluginName=this._getPluginName(r),this.options=this._mergeOptions(t),this._initialized=!1,this._registerInstance(),this._init()}}class a{get(e,t){let r=arguments.length>2&&void 0!==arguments[2]?arguments[2]:"application/json",i=this._createPreparedRequest("GET",e,r);return this._sendRequest(i,null,t)}post(e,t,r){let i=arguments.length>3&&void 0!==arguments[3]?arguments[3]:"application/json";i=this._getContentType(t,i);let n=this._createPreparedRequest("POST",e,i);return this._sendRequest(n,t,r)}delete(e,t,r){let i=arguments.length>3&&void 0!==arguments[3]?arguments[3]:"application/json";i=this._getContentType(t,i);let n=this._createPreparedRequest("DELETE",e,i);return this._sendRequest(n,t,r)}patch(e,t,r){let i=arguments.length>3&&void 0!==arguments[3]?arguments[3]:"application/json";i=this._getContentType(t,i);let n=this._createPreparedRequest("PATCH",e,i);return this._sendRequest(n,t,r)}abort(){if(this._request)return this._request.abort()}setErrorHandlingInternal(e){this._errorHandlingInternal=e}_registerOnLoaded(e,t){t&&(!0===this._errorHandlingInternal?(e.addEventListener("load",()=>{t(e.responseText,e)}),e.addEventListener("abort",()=>{console.warn("the request to ".concat(e.responseURL," was aborted"))}),e.addEventListener("error",()=>{console.warn("the request to ".concat(e.responseURL," failed with status ").concat(e.status))}),e.addEventListener("timeout",()=>{console.warn("the request to ".concat(e.responseURL," timed out"))})):e.addEventListener("loadend",()=>{t(e.responseText,e)}))}_sendRequest(e,t,r){return this._registerOnLoaded(e,r),e.send(t),e}_getContentType(e,t){return e instanceof FormData&&(t=!1),t}_createPreparedRequest(e,t,r){return this._request=new XMLHttpRequest,this._request.open(e,t),this._request.setRequestHeader("X-Requested-With","XMLHttpRequest"),r&&this._request.setRequestHeader("Content-type",r),this._request}constructor(){this._request=null,this._errorHandlingInternal=!1}}class l extends o{init(){this.client=new a,this.registerEvents(),this.fetchUploadedFiles()}registerEvents(){n.querySelectorAll(this.el,'[type="checkbox"]').forEach(e=>{e.classList.contains("method-type")&&e.addEventListener("change",this.checkboxValidation.bind(this,".form-method-type","","",event)),e.classList.contains("logo-placement")&&e.addEventListener("change",this.checkboxValidation.bind(this,".form-logo-placement",e.id,event)),e.classList.contains("delete-file")&&e.addEventListener("change",this.deleteFile.bind(this,e.dataset.logoPlacement)),e.classList.contains("delivery-type")&&e.addEventListener("change",this.checkboxValidation.bind(this,".form-delivery-duration","",e.id,event))}),n.querySelectorAll(this.el,'[type="file"]').forEach(e=>{e.classList.contains("logo-placement-file")&&e.addEventListener("change",this.checkboxValidation.bind(this,".form-logo-placement",e.dataset.logoPlacement,event))}),n.querySelectorAll(this.el,'input[type="text"], textarea').forEach(e=>{sessionStorage.getItem(e.name)&&(e.value=sessionStorage.getItem(e.name),e.dispatchEvent(new Event("change"))),e.addEventListener("change",()=>{sessionStorage.setItem(e.name,e.value)})}),n.querySelectorAll(this.el,".method-type, .delivery-type").forEach(e=>{let t=sessionStorage.getItem(e.id);t&&(e.checked="true"===t),e.addEventListener("change",()=>{sessionStorage.setItem(e.id,e.checked)})})}saveBase64FileData(e){let t=e.files,r={},i=e.id,n=this.options.allowedMimeTypes.split(",").map(e=>e.trim()),s=1048576*this.options.maxFileSize;t&&Promise.all(Array.from(t).map(e=>new Promise((t,i)=>{if(n.includes(e.type)||i("The mime type of the file is invalid ".concat(e.type,". Allowed mime types are ").concat(n)),e.size>s){let t=Number(e.size/1048576).toFixed(2);i("The file is too large (".concat(t," MB). Allowed maximum size is ").concat(this.options.maxFileSize," MB."))}let o=new FileReader;o.addEventListener("load",()=>{r[e.name]=o.result,t()}),o.addEventListener("error",()=>{i("Error reading file ".concat(e.name))}),o.readAsDataURL(e)}))).then(()=>{this.client.post(this.options.forwardTo,JSON.stringify({[i]:r}))}).catch(e=>{this.sendFileUploadErrorRequest(e)})}sendFileUploadErrorRequest(e){fetch(this.options.errorForwardTo,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({error:e})}).then(e=>{e.ok&&window.location.reload()})}addFilesToInput(e,t){let r=new DataTransfer;t.forEach(e=>r.items.add(e)),e.files=r.files,e.dispatchEvent(new Event("change"))}async fetchUploadedFiles(){let e=this.options.uploadedFiles;if(e)for(let t in e){let r=[],i=document.querySelector("#"+t);if(i){for(let[i,n]of Object.entries(e[t])){let e=await this.base64ToFile(i,n);r.push(e)}this.addFilesToInput(i,r)}}}async base64ToFile(e,t){let r=t.split(",")[0].match(/:(.*?);/)[1],i=await this.dataUrlToBytes(t);return new File([i],e,{type:r})}async dataUrlToBytes(e){let t=await fetch(e);return new Uint8Array(await t.arrayBuffer())}checkboxValidation(e){let t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:"",r=arguments.length>2&&void 0!==arguments[2]?arguments[2]:"",i=arguments.length>3?arguments[3]:void 0,n=document.querySelector(e),s=n.querySelectorAll("input[type=checkbox]:not(.delete-file)");n.querySelectorAll("input[type=checkbox]:checked:not(.delete-file)").length>0?(s.forEach(e=>e.removeAttribute("required")),".form-delivery-duration"===e&&this.uncheckOppositeOption(r)):s.forEach(e=>e.setAttribute("required","required")),".form-logo-placement"===e&&this.checkFileUpload(t,i)}checkFileUpload(e,t){let r=document.querySelector("#"+e),i=document.querySelector("#"+e+"File");"file"===t.target.type?0!==i.files.length&&(r.checked=!0,this.showFilename(e,i),this.saveBase64FileData(t.target)):"checkbox"!==t.target.type||r.checked||(i.value=null)}showFilename(e,t){document.querySelector("."+e+" .logo-upload-img").style.display="none",document.querySelector("."+e+" .upload-file-label").style.display="none",document.querySelector("#"+e+"File").style.display="none",document.querySelectorAll("."+e+" .uploaded-section").forEach(e=>e.style.display="block");for(var r="",i=0;i<t.files.length;i++)r+=t.files[i].name+"; ";document.querySelector("."+e+" .uploaded-filename").textContent=r,this.checkCheckboxes()}deleteFile(e){document.querySelector("."+e+" .logo-upload-img").style.display="block",document.querySelector("."+e+" .upload-file-label").style.display="block",document.querySelector("#"+e+"File").style.display="block",document.querySelectorAll("."+e+" .uploaded-section").forEach(e=>e.style.display="none");let t=document.querySelector("#"+e+"File");t.files=null,t.value=null,document.querySelector("#"+e).checked=!1,this.checkCheckboxes()}checkCheckboxes(){let e=document.querySelector(".form-logo-placement"),t=e.querySelectorAll("input[type=checkbox]:not(.delete-file)");e.querySelectorAll("input[type=checkbox]:checked:not(.delete-file)").length>0?t.forEach(e=>e.removeAttribute("required")):t.forEach(e=>e.setAttribute("required","required"))}uncheckOppositeOption(e){document.querySelector(".form-delivery-duration").querySelectorAll("input[type=checkbox]").forEach(t=>{t.id!==e&&(t.checked=!1,sessionStorage.setItem(t.id,!1))})}}l.options={uploadedFiles:{},forwardTo:!1,errorForwardTo:!1,maxFileSize:!1,allowedMimeTypes:!1};let c=window.PluginManager;c.register("InquiryPlugin",l,"[data-inquiry-plugin]"),c.register("InquiryPersonalPlugin",class extends o{init(){this.client=new a,this._registerEvents()}_registerEvents(){n.querySelectorAll(this.el,"input, select").forEach(e=>{sessionStorage.getItem(e.name)&&(e.value=sessionStorage.getItem(e.name),e.dispatchEvent(new Event("change"))),e.addEventListener("change",()=>{sessionStorage.setItem(e.name,e.value)})})}},"[data-inquiry-personal-plugin]"),c.register("InquiryFinishPlugin",class extends o{init(){sessionStorage.clear()}},"[data-inquiry-finish-plugin]")})()})();