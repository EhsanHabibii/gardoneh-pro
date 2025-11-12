jQuery(document).ready(function($) {
    // Tabs in settings page
    $('.lwt-settings-wrap .nav-tab').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').removeClass('active').addClass('hidden');
        $(this).addClass('nav-tab-active');
        $($(this).attr('href')).addClass('active').removeClass('hidden');
    });

    // Toggle form settings based on selection
    $('#lwt_form_type_selector').on('change', function() {
        if ($(this).val() === 'custom_form') {
            $('#lwt-custom-form-settings-wrapper').removeClass('hidden');
            $('#lwt-gravity-form-help').addClass('hidden');
        } else {
            $('#lwt-custom-form-settings-wrapper').addClass('hidden');
            $('#lwt-gravity-form-help').removeClass('hidden');
        }
    }).trigger('change');

    // Media Uploader
    function initialize_media_uploader(button_id, input_id) {
        $(document).on('click', button_id, function(e) {
            e.preventDefault();
            var image_frame = wp.media({ title: 'Select Media', multiple: false, library: { type: 'image' } });
            image_frame.on('select', function() {
                var media_attachment = image_frame.state().get('selection').first().toJSON();
                $(input_id).val(media_attachment.url);
            });
            image_frame.open();
        });
    }
    initialize_media_uploader('#lwt_upload_center_image_button', '#lwt_center_image_url');
    initialize_media_uploader('#lwt_upload_pointer_image_button', '#lwt_pointer_image_url');

    // AJAX Prize Management
    const prizeModal = $('#lwt-prize-form-modal');
    const prizeForm = $('#lwt-prize-form');
    const prizeList = $('#lwt-prizes-list');
    const prizeIdField = $('#lwt-prize-id');
    const prizeNameField = $('#lwt-prize-name');
    const prizeQuantityField = $('#lwt-prize-quantity');
    const prizeWeightField = $('#lwt-prize-weight');
    const isLosingPrizeField = $('#lwt-is-losing-prize');

    function loadPrizes() {
        prizeList.html('<tr><td colspan="5">در حال بارگذاری...</td></tr>');
        $.ajax({
            url: lwt_admin_ajax.ajax_url,
            type: 'POST',
            data: { action: 'lwt_get_prizes', nonce: lwt_admin_ajax.nonce },
            success: function(response) {
                if (response.success) {
                    renderPrizes(response.data);
                }
            }
        });
    }

    function renderPrizes(prizes) {
        prizeList.empty();
        if (prizes && prizes.length > 0) {
            prizes.forEach(function(prize) {
                const row = `
                    <tr class="entry-row" data-id="${prize.id}" data-name="${prize.name}" data-quantity="${prize.quantity}" data-weight="${prize.weight}" data-is-losing="${prize.is_losing_prize}">
                        <td class="title column-title has-row-actions column-primary">
                            <strong><a class="row-title" href="#">${prize.name}</a></strong>
                            <div class="row-actions">
                                <span class="edit"><a href="#" class="edit-prize">ویرایش</a> | </span>
                                <span class="trash"><a href="#" class="delete-prize">حذف</a></span>
                            </div>
                        </td>
                        <td>${prize.quantity}</td>
                        <td>${prize.weight}</td>
                        <td>${prize.is_losing_prize == '1' ? 'بله' : 'خیر'}</td>
                    </tr>`;
                prizeList.append(row);
            });
        } else {
            prizeList.html('<tr><td colspan="5">هیچ جایزه‌ای یافت نشد.</td></tr>');
        }
    }

    prizeForm.on('submit', function(e) {
        e.preventDefault();
        const prizeData = {
            action: 'lwt_save_prize', nonce: lwt_admin_ajax.nonce,
            id: prizeIdField.val(), name: prizeNameField.val(),
            quantity: prizeQuantityField.val(), weight: prizeWeightField.val(),
            is_losing: isLosingPrizeField.is(':checked')
        };
        $.ajax({
            url: lwt_admin_ajax.ajax_url,
            type: 'POST',
            data: prizeData,
            success: function(response) {
                if (response.success) {
                    closeModal();
                    loadPrizes();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    prizeList.on('click', '.edit-prize', function(e) {
        e.preventDefault();
        const row = $(this).closest('tr');
        prizeIdField.val(row.data('id'));
        prizeNameField.val(row.data('name'));
        prizeQuantityField.val(row.data('quantity'));
        prizeWeightField.val(row.data('weight'));
        isLosingPrizeField.prop('checked', row.data('is-losing') == '1');
        openModal();
    });

    prizeList.on('click', '.delete-prize', function(e) {
        e.preventDefault();
        if (!confirm('آیا از حذف این جایزه مطمئن هستید؟')) return;
        const id = $(this).closest('tr').data('id');
        $.ajax({
            url: lwt_admin_ajax.ajax_url,
            type: 'POST',
            data: { action: 'lwt_delete_prize', nonce: lwt_admin_ajax.nonce, id: id },
            success: function(response) {
                if (response.success) {
                    loadPrizes();
                }
            }
        });
    });

    $('#lwt-add-new-prize').on('click', function() { openModal(); });
    $('.lwt-close-modal').on('click', function() { closeModal(); });
    $(window).on('click', function(e) { if ($(e.target).is(prizeModal)) { closeModal(); } });

    function openModal() { prizeForm[0].reset(); prizeIdField.val(0); prizeModal.show(); }
    function closeModal() { prizeModal.hide(); prizeForm[0].reset(); prizeIdField.val(0); }

    if ($('#lwt-prizes-app').length) {
        loadPrizes();
    }

    // Datepicker for export
    $('.lwt-datepicker').datepicker({ dateFormat: 'yy-mm-dd' });
});
