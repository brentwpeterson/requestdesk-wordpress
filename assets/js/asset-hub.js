/**
 * RequestDesk Brand Asset Hub — admin grid behavior.
 * Depends on: jQuery, wp.media (enqueued via wp_enqueue_media), RD_ASSET_HUB.
 */
(function ($) {
    'use strict';

    var CFG = window.RD_ASSET_HUB || {};
    var $grid = $('#rd-asset-grid');

    function post(action, data) {
        return $.post(CFG.ajaxUrl, $.extend({
            action: action,
            nonce: CFG.nonce
        }, data));
    }

    /* ---- Add Brand Asset: open the Media Library frame ---- */
    var frame;
    $('#rd-asset-add').on('click', function (e) {
        e.preventDefault();
        if (frame) {
            frame.open();
            return;
        }
        frame = wp.media({
            title: 'Select or upload brand assets',
            button: { text: 'Add to Brand Assets' },
            library: { type: 'image' },
            multiple: true
        });

        frame.on('select', function () {
            var selection = frame.state().get('selection').toJSON();
            selection.forEach(function (att) {
                // Skip ones already on the grid.
                if ($grid.find('.rd-asset-card[data-id="' + att.id + '"]').length) {
                    return;
                }
                post('requestdesk_asset_mark', { id: att.id }).done(function (res) {
                    if (res && res.success && res.data && res.data.html) {
                        $('#rd-asset-empty').remove();
                        $grid.prepend(res.data.html);
                    }
                });
            });
        });

        frame.open();
    });

    /* ---- Load demo ads (bundled with the plugin) ---- */
    $('#rd-asset-demos').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Loading…');
        post('requestdesk_load_demos', {}).done(function (res) {
            if (res && res.success && res.data && res.data.cards) {
                $('#rd-asset-empty').remove();
                res.data.cards.forEach(function (html) {
                    var id = $(html).data('id');
                    if (id && $grid.find('.rd-asset-card[data-id="' + id + '"]').length) {
                        return; // already on the grid
                    }
                    $grid.prepend(html);
                });
            }
        }).always(function () {
            $btn.prop('disabled', false).text('Load demo ads');
        });
    });

    /* ---- Copy buttons (Download / Image URL / Embed / Shortcode) ---- */
    $grid.on('click', '.rd-copy', function () {
        var $btn = $(this);
        var text = $btn.attr('data-copy') || '';
        var done = function () {
            var label = $btn.text();
            $btn.addClass('rd-copied').text('Copied');
            setTimeout(function () {
                $btn.removeClass('rd-copied').text(label);
            }, 1200);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () {
                fallbackCopy(text); done();
            });
        } else {
            fallbackCopy(text); done();
        }
    });

    function fallbackCopy(text) {
        var $tmp = $('<textarea>').val(text).css({
            position: 'fixed', top: '-1000px'
        }).appendTo('body');
        $tmp[0].select();
        try { document.execCommand('copy'); } catch (e) {}
        $tmp.remove();
    }

    /* ---- Save link URL + alt ---- */
    $grid.on('click', '.rd-asset-savebtn', function () {
        var $card = $(this).closest('.rd-asset-card');
        var id = $card.data('id');
        var link = $card.find('.rd-asset-link').val();
        var alt = $card.find('.rd-asset-alt').val();
        var rotation = $card.find('.rd-asset-rotate').is(':checked') ? 1 : 0;
        var placement = $card.find('.rd-asset-placement').val();
        var sponsored = $card.find('.rd-asset-sponsored').is(':checked') ? 1 : 0;
        var $saved = $card.find('.rd-asset-saved');

        $saved.text('Saving…');
        post('requestdesk_asset_save', { id: id, link: link, alt: alt, rotation: rotation, placement: placement, sponsored: sponsored }).done(function (res) {
            if (res && res.success) {
                // Refresh the embed-copy button with the new snippet.
                if (res.data && typeof res.data.embed !== 'undefined') {
                    $card.find('.rd-copy-embed').attr('data-copy', res.data.embed);
                }
                $saved.text('Saved');
            } else {
                $saved.text('Error');
            }
            setTimeout(function () { $saved.text(''); }, 1500);
        }).fail(function () {
            $saved.text('Error');
        });
    });

    /* ---- Remove from hub (keeps the Media Library file) ---- */
    $grid.on('click', '.rd-asset-remove', function (e) {
        e.preventDefault();
        var $card = $(this).closest('.rd-asset-card');
        var id = $card.data('id');
        if (!window.confirm('Remove this asset from the Brand Assets hub? The file stays in your Media Library.')) {
            return;
        }
        post('requestdesk_asset_remove', { id: id }).done(function () {
            $card.fadeOut(150, function () {
                $card.remove();
                if (!$grid.find('.rd-asset-card').length) {
                    $grid.html('<div class="rd-asset-empty" id="rd-asset-empty"><p>No brand assets yet. Click <strong>Add Brand Asset</strong> to pull one in from the Media Library (or upload a new one).</p></div>');
                }
            });
        });
    });

})(jQuery);
