jQuery(document).ready(function($) {
    $('body').on('click', '.grp-read-more-btn', function() {
        const $btn = $(this);
        const $textContainer = $btn.siblings('.grp-review-text');

        $textContainer.toggleClass('expanded');

        if ($textContainer.hasClass('expanded')) {
            $btn.text($btn.data('less'));
        } else {
            $btn.text($btn.data('more'));
        }
    });

    // --- LOGIC FOR SLIDER & BADGE ---
    // Slider Arrows
    $('.grp-slider-arrow.next').click(function() {
        $(this).siblings('.grp-slider-track').animate({scrollLeft: '+=320'}, 300);
    });
    $('.grp-slider-arrow.prev').click(function() {
        $(this).siblings('.grp-slider-track').animate({scrollLeft: '-=320'}, 300);
    });

    // Badge Toggle
    $('.grp-badge-trigger').click(function() {
        $('.grp-badge-modal').toggleClass('open');
    });

    $('.grp-badge-close').click(function() {
        $('.grp-badge-modal').removeClass('open');
    });

    // LOAD MORE (AJAX)
    $('.grp-load-more-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const offset = $btn.data('offset');
        const limit = $btn.data('limit') || gprJs.reviewsLimit;
        const nonce = $btn.data('nonce');
        const placeId = $btn.data('place-id');
        const layout = $btn.data('layout');
        const container = $btn.closest('.grp-container').find('.grp-grid, .grp-list-view, .grp-slider-track');

        if($btn.hasClass('loading')) {
            return;
        }

        $btn.addClass('loading').text(gprJs.loadingText);

        $.post(gprJs.ajaxUrl, {
            action: 'grp_load_more',
            nonce: nonce,
            offset: offset,
            limit: limit,
            place_id: placeId,
            layout: layout
        }, function(res) {
            $btn.removeClass('loading').text(gprJs.buttonText);
            if (res.success) {
                container.append(res.data.html);
                $btn.data('offset', offset + limit);

                // Slider only logic
                if (container.hasClass('grp-slider-track')) {
                    container.animate({scrollLeft: '+=200'}, 500);
                }

                let has_more = res.data.has_more || '';
                if(!has_more) {
                    $btn.hide();
                }
            } else {
                // alert(res.data.message || 'Error');
                console.log(res.data.message || 'Error');
                $btn.hide();
            }
        });
    });
});