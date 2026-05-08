/* OCCI Parish Register - Admin JS */
jQuery(document).ready(function($) {

    // Confirm delete actions
    $(document).on('click', '.occi-delete', function(e) {
        if (!confirm('This will permanently delete this record. This action cannot be undone. Are you sure?')) {
            e.preventDefault();
        }
    });

    // Photo uploader (WP media library)
    var mediaFrames = {};

    $(document).on('click', '.occi-select-photo', function(e) {
        e.preventDefault();
        var uid = $(this).data('uid');

        if (mediaFrames[uid]) {
            mediaFrames[uid].open();
            return;
        }

        mediaFrames[uid] = wp.media({
            title: 'Select Photo',
            button: { text: 'Use this photo' },
            multiple: false,
            library: { type: 'image' }
        });

        mediaFrames[uid].on('select', function() {
            var attachment = mediaFrames[uid].state().get('selection').first().toJSON();
            var preview    = attachment.sizes && attachment.sizes.medium
                ? attachment.sizes.medium.url
                : attachment.url;
            $('#' + uid + '_id').val(attachment.id);
            $('#' + uid + '_img').attr('src', preview);
            $('#' + uid + '_preview').show();
            $('[data-uid="' + uid + '"].occi-select-photo').text('Change Photo');
            $('[data-uid="' + uid + '"].occi-remove-photo').show();
        });

        mediaFrames[uid].open();
    });

    $(document).on('click', '.occi-remove-photo', function(e) {
        e.preventDefault();
        var uid = $(this).data('uid');
        $('#' + uid + '_id').val('');
        $('#' + uid + '_preview').hide();
        $('#' + uid + '_img').attr('src', '');
        $('[data-uid="' + uid + '"].occi-select-photo').text('Select Photo');
        $(this).hide();
    });

});
