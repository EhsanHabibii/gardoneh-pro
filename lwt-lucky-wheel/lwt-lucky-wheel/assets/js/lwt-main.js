var theWheel; // Make the wheel object global

jQuery(document).ready(function($) {
    if ($('#lwt-container-wrapper').length === 0) return;
    if (typeof lwt_settings === 'undefined' || !lwt_settings.prizes || !lwt_settings.prizes.length) {
        $('#lwt-container-wrapper').html('<p class="lwt-error-message">گردونه در دسترس نیست. لطفاً جوایز را در پنل مدیریت تعریف کنید.</p>');
        return;
    }
    
    initializeWheel();

    // Handler for Gravity Forms
    if (lwt_settings.form_type === 'gravity_forms') {
        $(document).on('gform_confirmation_loaded', function(event, formId){
            if (typeof lwt_gf_config !== 'undefined' && formId == lwt_gf_config.form_id) {
                const confirmationWrapper = $('#gform_confirmation_wrapper_' + formId);
                const entryId = confirmationWrapper.data('lwt-entry-id');
                if (!entryId) { console.error('Lucky Wheel: Could not find Entry ID.'); return; }
                $('#gform_wrapper_' + formId).slideUp();
                $.ajax({
                    url: lwt_settings.ajax_url, type: 'POST',
                    data: { action: 'lwt_get_spin_result', nonce: lwt_settings.gf_nonce, entry_id: entryId },
                    success: function(response) {
                        if (response.success) { startTheWheelAnimation(response.data); } 
                        else { showMessage(response.data.message || 'نتیجه‌ای برای چرخاندن یافت نشد.', 'error'); }
                    },
                    error: function() { showMessage('خطا در دریافت نتیجه قرعه‌کشی.', 'error'); }
                });
            }
        });
    } 
    // Handler for our Custom Form
    else if (lwt_settings.form_type === 'custom_form') {
        $(document).on('submit', '#lwt-custom-form', function(e) {
            e.preventDefault();
            let formData = $(this).serialize();
            let spinButton = $(this).find('button[type="submit"]');
            
            spinButton.prop('disabled', true).text('در حال بررسی...');
            
            $.ajax({
                url: lwt_settings.ajax_url,
                type: 'POST',
                data: formData + '&action=lwt_custom_form_spin&nonce=' + lwt_settings.nonce,
                success: function(response) {
                    if (response.success) {
                        spinButton.closest('form').slideUp();
                        startTheWheelAnimation(response.data);
                    } else {
                        showMessage(response.data.message, 'error');
                        spinButton.prop('disabled', false).text('دوباره امتحان کن');
                    }
                },
                error: function() {
                    showMessage('خطای سرور رخ داد.', 'error');
                    spinButton.prop('disabled', false).text('دوباره امتحان کن');
                }
            });
        });
    }
});

function initializeWheel() {
    let segments = [];
    for (let i = 0; i < lwt_settings.prizes.length; i++) {
        segments.push({ 'fillStyle': lwt_settings.colors[i % lwt_settings.colors.length], 'text': lwt_settings.prizes[i].name });
    }
    const wheelSize = parseInt(lwt_settings.wheel_size);
    theWheel = new Winwheel({
        'canvasId': 'lwt-canvas', 'pointerAngle': 90, 'numSegments': segments.length, 'segments': segments,
        'outerRadius': (wheelSize / 2) * 0.97, 'innerRadius': (wheelSize / 2) * 0.15, 
        'textFontSize': parseInt(lwt_settings.font_size), 'textFontFamily': 'Vazirmatn, sans-serif',
        'textFillStyle': '#ffffff', 'textMargin': 20, 'lineWidth': 0, 'strokeStyle': 'transparent',
        'textOrientation': 'horizontal', 'textAlignment': 'outer',
        'animation': { 'type': 'spinToStop', 'duration': 10, 'spins': 12, 'callbackFinished': alertPrize }
    });
    createStaticDots();
}

function createStaticDots() {
    const numSegments = theWheel.numSegments; if (numSegments === 0) return;
    const dotsContainer = jQuery('#lwt-static-dots'); dotsContainer.empty();
    const angleStep = 360 / numSegments;
    for (let i = 0; i < numSegments; i++) {
        const dotAngle = i * angleStep;
        const dot = jQuery('<div class="lwt-static-dot"></div>');
        dot.css('transform', `rotate(${dotAngle}deg) translate(-50%, -50%)`);
        dotsContainer.append(dot);
    }
}

function startTheWheelAnimation(prizeData) {
    const messageBox = jQuery('#lwt-message');
    const winningSegment = theWheel.segments.find(s => s && s.text === prizeData.prize_name);
    if (winningSegment) {
         let middleAngle = winningSegment.startAngle + ((winningSegment.endAngle - winningSegment.startAngle) / 2);
         theWheel.animation.stopAngle = middleAngle;
         theWheel.startAnimation();
         messageBox.data('prize_data', prizeData);
    } else { showMessage('خطای داخلی: جایزه یافت نشد.', 'error'); }
}

function alertPrize() {
    const messageBox = jQuery('#lwt-message');
    const prizeData = messageBox.data('prize_data');
    const successMessage = lwt_settings.success_message || 'تبریک! شما برنده {prize_name} شدید!';
    const losingMessage = lwt_settings.losing_message || 'متاسفانه برنده نشدید.';

    if (prizeData.is_winner) {
        showMessage(successMessage.replace('{prize_name}', '<strong>' + prizeData.prize_name + '</strong>'), 'success');
    } else {
        showMessage(losingMessage, 'error');
    }
}

function showMessage(message, type) {
    const messageBox = jQuery('#lwt-message');
    messageBox.html(message).removeClass('success error').addClass(type).show();
}
