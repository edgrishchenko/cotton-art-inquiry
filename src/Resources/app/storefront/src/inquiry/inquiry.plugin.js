import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import HttpClient from 'src/service/http-client.service';

export default class InquiryPlugin extends Plugin {
    static options = {
        uploadedFiles: {},
        forwardTo: false,
        errorForwardTo: false,
        maxFileSize: false,
        allowedMimeTypes: false,
    };

    init() {
        this.client = new HttpClient();

        this.registerEvents();

        this.fetchUploadedFiles();
    }

    registerEvents() {
        const fileCheckboxes = DomAccess.querySelectorAll(this.el, '[type="checkbox"]');
        fileCheckboxes.forEach((checkbox) => {
            if (checkbox.classList.contains('method-type')) {
                checkbox.addEventListener('change', this.checkboxValidation.bind(this, '.form-method-type', '', '', event));
            }

            if (checkbox.classList.contains('logo-placement')) {
                checkbox.addEventListener('change', this.checkboxValidation.bind(this, '.form-logo-placement', checkbox.id, event));
            }

            if (checkbox.classList.contains('delete-file')) {
                checkbox.addEventListener('change', this.deleteFile.bind(this, checkbox.dataset.logoPlacement));
            }

            if (checkbox.classList.contains('delivery-type')) {
                checkbox.addEventListener('change', this.checkboxValidation.bind(this, '.form-delivery-duration', '', checkbox.id, event));
            }
        });

        const fileInputs = DomAccess.querySelectorAll(this.el, '[type="file"]');
        fileInputs.forEach((fileInput) => {
            if (fileInput.classList.contains('logo-placement-file')) {
                fileInput.addEventListener('change', this.checkboxValidation.bind(this, '.form-logo-placement', fileInput.dataset.logoPlacement, event));
            }
        });

        const inputs = DomAccess.querySelectorAll(this.el, 'input[type="text"], textarea');
        inputs.forEach((input) => {
            if (sessionStorage.getItem(input.name)) {
                input.value = sessionStorage.getItem(input.name);
                input.dispatchEvent(new Event('change'));
            }
            input.addEventListener('change', () => {
                sessionStorage.setItem(input.name, input.value);
            });
        });

        const checkboxes = DomAccess.querySelectorAll(this.el, '.method-type, .delivery-type');
        checkboxes.forEach((checkbox) => {
            const storedValue = sessionStorage.getItem(checkbox.id);
            if (storedValue) {
                checkbox.checked = storedValue === 'true';
            }
            checkbox.addEventListener('change', () => {
                sessionStorage.setItem(checkbox.id, checkbox.checked);
            });
        });
    }

    saveBase64FileData(fileInput) {
        const files = fileInput.files;
        const base64FileData = {};
        const inputFileId = fileInput.id;

        const allowedMimeTypes= this.options.allowedMimeTypes.split(',').map(type => type.trim());
        const maxFileSize = this.options.maxFileSize * 1024 * 1024;

        if (files) {
            const fileReadPromises = Array.from(files).map((file) => {
                return new Promise((resolve, reject) => {
                    if (!allowedMimeTypes.includes(file.type)) {
                        reject(`The mime type of the file is invalid ${file.type}. Allowed mime types are ${allowedMimeTypes}`);
                    }

                    if (file.size > maxFileSize) {
                        const size = Number(file.size / (1024 * 1024)).toFixed(2);
                        reject(`The file is too large (${size} MB). Allowed maximum size is ${this.options.maxFileSize} MB.`);
                    }

                    const reader = new FileReader();

                    reader.addEventListener("load", () => {
                        base64FileData[file.name] = reader.result;
                        resolve();
                    });

                    reader.addEventListener("error", () => {
                        reject(`Error reading file ${file.name}`);
                    });

                    reader.readAsDataURL(file);
                });
            });

            Promise.all(fileReadPromises)
                .then(() => {
                    const payload = {
                        [inputFileId]: base64FileData
                    };

                    this.client.post(this.options.forwardTo, JSON.stringify(payload));
                })
                .catch(error => {
                    this.sendFileUploadErrorRequest(error);
                });
        }
    }

    sendFileUploadErrorRequest(message) {
        fetch(this.options.errorForwardTo, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ error: message }),
        })
        .then(response => {
            if (response.ok) {
                window.location.reload();
            }
        })
    }

    addFilesToInput(inputField, files) {
        const dataTransfer = new DataTransfer();

        files.forEach((file) => dataTransfer.items.add(file));

        inputField.files = dataTransfer.files;
        inputField.dispatchEvent(new Event('change'));
    }

    async fetchUploadedFiles() {
        const uploadedFiles = this.options.uploadedFiles;

        if (uploadedFiles) {
            for (const inputFieldId in uploadedFiles) {
                const files = [];
                const fileInput = document.querySelector('#' + inputFieldId);

                if (fileInput) {
                    const filesData = uploadedFiles[inputFieldId];

                    for (const [filename, base64] of Object.entries(filesData)) {
                        const file = await this.base64ToFile(filename, base64);
                        files.push(file);
                    }

                    this.addFilesToInput(fileInput, files);
                }
            }
        }
    }

    async base64ToFile(filename, base64Data) {
        const arr = base64Data.split(","), // Split the base64 string to get the MIME type and the data
            mime = arr[0].match(/:(.*?);/)[1]; // Extract MIME type

        const u8arr = await this.dataUrlToBytes(base64Data);

        return new File([u8arr], filename, {type: mime});
    }

    async dataUrlToBytes(dataUrl) {
        const res = await fetch(dataUrl);
        return new Uint8Array(await res.arrayBuffer());
    }

    checkboxValidation(parentClass, logoPlacement = '', deliveryType = '', event) {
        const parentForm = document.querySelector(parentClass);
        const checkboxes = parentForm.querySelectorAll('input[type=checkbox]:not(.delete-file)');

        if (parentForm.querySelectorAll('input[type=checkbox]:checked:not(.delete-file)').length > 0) {
            checkboxes.forEach((element) => element.removeAttribute('required'));

            if (parentClass === '.form-delivery-duration') {
                this.uncheckOppositeOption(deliveryType);
            }
        } else {
            checkboxes.forEach((element) => element.setAttribute('required', 'required'));
        }

        if (parentClass === '.form-logo-placement') {
            this.checkFileUpload(logoPlacement, event);
        }
    }

    checkFileUpload(logoPlacement, event) {
        const fileCheckbox = document.querySelector('#' + logoPlacement);
        const fileInput = document.querySelector('#' + logoPlacement + 'File');

        if (event.target.type === 'file') {
            if (fileInput.files.length !== 0) {
                fileCheckbox.checked = true;
                this.showFilename(logoPlacement, fileInput);
                this.saveBase64FileData(event.target);
            }
        } else if (event.target.type === 'checkbox') {
            if (!fileCheckbox.checked) {
                fileInput.value = null;
            }
        }
    }

    showFilename(logoPlacement, fileInput) {
        document.querySelector('.' + logoPlacement + ' .logo-upload-img').style.display = 'none';
        document.querySelector('.' + logoPlacement + ' .upload-file-label').style.display = 'none';
        document.querySelector('#' + logoPlacement + 'File').style.display = 'none';

        var uploadedSections = document.querySelectorAll('.' + logoPlacement + ' .uploaded-section');
        uploadedSections.forEach((element) => element.style.display = 'block');

        var filenames = '';
        for (var i = 0; i < fileInput.files.length; i++) {
            filenames += fileInput.files[i].name + '; ';
        }
        document.querySelector('.' + logoPlacement + ' .uploaded-filename').textContent = filenames;

        this.checkCheckboxes();
    }

    deleteFile(logoPlacement) {
        document.querySelector('.' + logoPlacement + ' .logo-upload-img').style.display = 'block';
        document.querySelector('.' + logoPlacement + ' .upload-file-label').style.display = 'block';
        document.querySelector('#' + logoPlacement + 'File').style.display = 'block';

        var uploadedSections = document.querySelectorAll('.' + logoPlacement + ' .uploaded-section');
        uploadedSections.forEach((element) => element.style.display = 'none');

        const fileInput = document.querySelector('#' + logoPlacement + 'File');
        fileInput.files = null;
        fileInput.value = null;
        document.querySelector('#' + logoPlacement).checked = false;

        this.checkCheckboxes();
    }

    checkCheckboxes() {
        const parentForm = document.querySelector('.form-logo-placement');
        const checkboxes = parentForm.querySelectorAll('input[type=checkbox]:not(.delete-file)');

        if (parentForm.querySelectorAll('input[type=checkbox]:checked:not(.delete-file)').length > 0) {
            checkboxes.forEach((element) => element.removeAttribute('required'));
        } else {
            checkboxes.forEach((element) => element.setAttribute('required', 'required'));
        }
    }

    uncheckOppositeOption(deliveryType) {
        const deliveryForm = document.querySelector('.form-delivery-duration');
        const checkboxes = deliveryForm.querySelectorAll('input[type=checkbox]');

        checkboxes.forEach((element) => {
            if (element.id !== deliveryType) {
                element.checked = false;
                sessionStorage.setItem(element.id, false);
            }
        });
    }
}

