define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/confirm',
    'jquery/file-uploader'
], function ($, $t, alert, confirm) {
    'use strict';

    /**
     * ImageSearch uploader widget
     */
    return function (config, element) {
        // Конфигурация по умолчанию
        var options = {
            uploadUrl: config.uploadUrl || '',
            maxFileSize: config.maxFileSize || 20971520,
            allowedExtensions: config.allowedExtensions || ['jpg', 'jpeg', 'png', 'gif'],
            progressBar: config.progressBar || '#upload-progress-bar',
            previewContainer: config.previewContainer || '#image-preview',
            uploadButton: config.uploadButton || '#upload-button',
            dropZone: config.dropZone || '#drop-zone'
        };

        // Элементы DOM
        var $form = $(element);
        var $fileInput = $form.find('input[type="file"]');
        var $dropZone = $(options.dropZone);
        var $uploadButton = $(options.uploadButton);
        var $progressBar = $(options.progressBar);
        var $previewContainer = $(options.previewContainer);
        var $progressContainer = $progressBar.closest('.progress-container');

        // Логирование для отладки
        console.log('ImageSearch: Initializing with options', options);
        console.log('ImageSearch: Form found', $form.length);
        console.log('ImageSearch: File input found', $fileInput.length);
        console.log('ImageSearch: Drop zone found', $dropZone.length);
        console.log('ImageSearch: Upload button found', $uploadButton.length);

        // Проверка наличия необходимых элементов
        if (!$fileInput.length) {
            console.error('ImageSearch: File input not found');
            return;
        }

        if (!$uploadButton.length) {
            console.error('ImageSearch: Upload button not found');
            return;
        }

        /**
         * Показать превью изображения
         */
        function showPreview(file) {
            var reader = new FileReader();
            
            reader.onload = function (e) {
                var html = '<img src="' + e.target.result + '" class="preview-image" alt="Preview"/>';
                $previewContainer.html(html).show();
                $dropZone.find('.drop-zone-content').hide();
                console.log('ImageSearch: Preview shown for', file.name);
            };
            
            reader.readAsDataURL(file);
        }

        /**
         * Очистить превью
         */
        function clearPreview() {
            $previewContainer.empty().hide();
            $dropZone.find('.drop-zone-content').show();
            $progressContainer.hide();
            $progressBar.css('width', '0%').text('');
            console.log('ImageSearch: Preview cleared');
        }

        /**
         * Отключить элементы управления загрузкой
         */
        function disableUpload() {
            $uploadButton.prop('disabled', true).addClass('disabled');
            $fileInput.prop('disabled', true);
            console.log('ImageSearch: Upload disabled');
        }

        /**
         * Включить элементы управления загрузкой
         */
        function enableUpload() {
            $uploadButton.prop('disabled', false).removeClass('disabled');
            $fileInput.prop('disabled', false);
            console.log('ImageSearch: Upload enabled');
        }

        // Инициализация jQuery File Upload
        $fileInput.fileupload({
            url: options.uploadUrl,
            dataType: 'json',
            maxFileSize: options.maxFileSize,
            acceptFileTypes: new RegExp('(\.|\/)(' + options.allowedExtensions.join('|') + ')$', 'i'),
            paramName: 'image',
            
            // Данные формы (form key)
            formData: function() {
                return [{
                    name: 'form_key',
                    value: $form.find('input[name="form_key"]').val() || window.FORM_KEY || ''
                }];
            },

            // Прогресс загрузки
            progressall: function (e, data) {
                var progress = parseInt(data.loaded / data.total * 100, 10);
                $progressBar
                    .css('width', progress + '%')
                    .attr('aria-valuenow', progress)
                    .text(progress + '%');
                $progressContainer.show();
                console.log('ImageSearch: Upload progress', progress + '%');
            },

            // Добавление файла (когда пользователь выбрал файл)
            add: function (e, data) {
                var file = data.files[0];
                var extension = file.name.split('.').pop().toLowerCase();
                
                console.log('ImageSearch: File selected', file.name, file.size + ' bytes');

                // Валидация типа файла
                if (options.allowedExtensions.indexOf(extension) === -1) {
                    console.warn('ImageSearch: Invalid file type', extension);
                    alert({
                        content: $t('File type not allowed. Please upload JPG, PNG or GIF.')
                    });
                    return;
                }

                // Валидация размера файла
                if (file.size > options.maxFileSize) {
                    console.warn('ImageSearch: File too large', file.size, 'max', options.maxFileSize);
                    alert({
                        content: $t('File is too large. Maximum size is 20MB.')
                    });
                    return;
                }

                // Показываем превью
                showPreview(file);

                // Подтверждение загрузки
                confirm({
                    content: $t('Do you want to search with this image?'),
                    actions: {
                        confirm: function () {
                            console.log('ImageSearch: Upload confirmed');
                            data.submit();
                            disableUpload();
                        },
                        cancel: function () {
                            console.log('ImageSearch: Upload cancelled');
                            clearPreview();
                        }
                    }
                });
            },

            // Успешная загрузка
            done: function (e, data) {
                console.log('ImageSearch: Upload completed', data.result);
                
                if (data.result && data.result.success) {
                    if (data.result.redirect_url) {
                        console.log('ImageSearch: Redirecting to', data.result.redirect_url);
                        window.location.href = data.result.redirect_url;
                    } else {
                        enableUpload();
                        alert({
                            content: $t('Image uploaded successfully, but no redirect URL provided.')
                        });
                        clearPreview();
                    }
                } else {
                    enableUpload();
                    alert({
                        content: data.result ? data.result.message : $t('Error uploading image.')
                    });
                    clearPreview();
                }
            },

            // Ошибка загрузки
            fail: function (e, data) {
                console.error('ImageSearch: Upload failed', data.errorThrown, data.textStatus);
                enableUpload();
                alert({
                    content: $t('Upload failed. Please try again.') + ' (' + (data.errorThrown || 'Unknown error') + ')'
                });
                clearPreview();
            },

            // Всегда выполняется после done или fail
            always: function () {
                console.log('ImageSearch: Upload process completed');
                enableUpload();
            }
        });

        // Обработчик клика по кнопке "Choose File"
        $uploadButton.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('ImageSearch: Upload button clicked');
            $fileInput.click();
        });

        // Обработчики Drag & Drop
        $dropZone.on('dragover', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });

        $dropZone.on('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });

        $dropZone.on('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            
            // Передаем файлы в file input
            if (e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files.length) {
                console.log('ImageSearch: Files dropped', e.originalEvent.dataTransfer.files.length);
                $fileInput[0].files = e.originalEvent.dataTransfer.files;
                $fileInput.trigger('change'); // Триггерим change событие для fileupload
            }
        });

        // Дополнительная обработка изменения файла
        $fileInput.on('change', function () {
            if (this.files.length) {
                console.log('ImageSearch: File input change', this.files[0].name);
                // fileupload сам обработает это событие
            }
        });

        console.log('ImageSearch: Widget initialized successfully');
        
        // Возвращаем публичное API (опционально)
        return {
            enableUpload: enableUpload,
            disableUpload: disableUpload,
            clearPreview: clearPreview
        };
    };
});