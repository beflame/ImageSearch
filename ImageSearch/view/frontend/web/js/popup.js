define([
    'jquery',
    'Magento_Ui/js/modal/modal'
], function ($, modal) {
    'use strict';

    return function (config, element) {
        var $popup = $(element);
        var $trigger = $(config.triggerSelector);
        
        console.log('ImageSearch Popup: Initializing', config);
        console.log('ImageSearch Popup: Trigger found', $trigger.length);
        console.log('ImageSearch Popup: Popup found', $popup.length);

        if (!$trigger.length || !$popup.length) {
            console.error('ImageSearch Popup: Required elements not found');
            return;
        }

        // Настройки модального окна
        var modalOptions = {
            type: 'popup',
            title: config.popupTitle || 'Search by Image',
            modalClass: 'image-search-modal',
            responsive: true,
            innerScroll: true,
            buttons: [{
                text: $.mage.__('Close'),
                class: 'action close',
                click: function () {
                    this.closeModal();
                    // Очищаем форму при закрытии
                    var $form = $popup.find('#image-upload-form');
                    var $fileInput = $form.find('input[type="file"]');
                    var $preview = $popup.find('#image-preview');
                    var $progress = $popup.find('#upload-progress-bar');
                    
                    $fileInput.val('');
                    $preview.empty().hide();
                    $popup.find('.drop-zone-content').show();
                    $progress.closest('.progress-container').hide();
                    $progress.css('width', '0%').text('');
                }
            }]
        };

        // Инициализация модального окна
        var popup = modal(modalOptions, $popup);

        // Открытие по клику на иконку
        $trigger.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('ImageSearch Popup: Trigger clicked');
            $popup.modal('openModal');
        });

        // Дополнительная очистка при закрытии
        $popup.on('modalclosed', function () {
            var $form = $popup.find('#image-upload-form');
            var $fileInput = $form.find('input[type="file"]');
            var $preview = $popup.find('#image-preview');
            var $progress = $popup.find('#upload-progress-bar');
            
            $fileInput.val('');
            $preview.empty().hide();
            $popup.find('.drop-zone-content').show();
            $progress.closest('.progress-container').hide();
            $progress.css('width', '0%').text('');
        });

        console.log('ImageSearch Popup: Initialized successfully');
    };
});
