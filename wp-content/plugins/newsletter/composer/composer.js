var target = null;
let tnp_container = null;
var tnp_backup_block_options;

// add delete buttons
jQuery.fn.add_block_delete = function () {
    this.find('.tnpc-row-delete').on('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        NewsletterComposer.close_block_options();
        jQuery(this).closest("table.tnpc-row-block").remove();
    });
};

// add edit button
jQuery.fn.add_block_edit = function () {
    this.find('.tnpc-row-edit').on('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();

        tnp_container = jQuery(this).closest("table.tnpc-row-block");
        if (!tnp_container) {
            alert("This is deprecated block version and cannot be edited. Please replace it with a new one.");
            return;
        }

        target = tnp_container.find('td.edit-block');


        NewsletterComposer.hide_block_options();

        var options = tnp_container.find(".tnpc-block-content").attr("data-json");

        var data = {
            action: "tnpc_block_form",
            id: tnp_container.data("id"),
            context_type: tnp_context_type,
            options: options
        };

        NewsletterComposer.add_composer_options(data);

        //builderAreaHelper.lock();
        jQuery.ajax({
            url: ajaxurl,
            method: 'POST',
            data: data,
            //async: false,
            success: function (response) {
                jQuery("#tnpc-block-options-form").html(response.form);
                jQuery("#tnpc-block-options-title").html(response.title);
                tnp_backup_block_options = jQuery("#tnpc-block-options-form :input").serializeArray();
                NewsletterComposer.show_block_options();
            }
        });

    });
};

// add clone button
jQuery.fn.add_block_clone = function () {
    this.find('.tnpc-row-clone').on('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        NewsletterComposer.close_block_options();

        let row = jQuery(this).closest("table.tnpc-row-block");

        let new_row = row.clone();
        new_row.add_block_actions();
        new_row.insertAfter(row);
    });
};

jQuery.fn.add_block_actions = function () {
    this.find(".tnpc-row-actions").remove();

    let html = '<div class="tnpc-row-actions">';
    html += '<div class="tnpc-row-action tnpc-row-edit" title="Edit"><span class="dashicons dashicons-edit"></span></div>';
    html += '<div class="tnpc-row-action tnpc-row-clone" title="Clone"><span class="dashicons dashicons-admin-page"></span></div>';
    html += '<div class="tnpc-row-action tnpc-row-delete" title="Delete"><span class="dashicons dashicons-trash"></span></div>';
    html += '</div>';
    this.append(html);
    this.add_block_delete();
    this.add_block_edit();
    this.add_block_clone();
};

const NewsletterComposer = {
    initialized: false,
    current_view: 'desktop',
    message_field: jQuery('#options-message'),

    init: function () {

        var content = this.message_field.val();
        content = decodeURIComponent(atob(content));

        if (!content) {
            jQuery('#templates-modal').modal();
        } else {
            this.set_content(content);
            this.init_builder();
        }

        document.getElementById("defaultOpen").click();

        this.init_block_options_form();
        this.init_composer_options_form();

        // Update the encoded message field on container form submit
        jQuery('#tnpb-main').closest('form').on('submit', function () {
            jQuery("#tnpc-block-options-form").html(''); // To avoid the submission of the current block options
            NewsletterComposer.save();
        });

        this.init_change_view();

        this.refresh_style();

        this.initialized = true;
    },

    save: function () {
        if (!this.initialized) {
            console.error('Composer still not initialized');
            return;
        }

        if (window.tinymce) {
            window.tinymce.triggerSave();
        }
        this.message_field.val(this.get_content());
    },

    // The appearance of the builder when the global settings are changed (new template, ...)
    refresh_style: function () {
        jQuery('#tnpb-content').css('background-color', document.getElementById('options-options_composer_background').value);
        let padding = document.getElementById('options-options_composer_padding').value;
        if (padding !== '') {
            padding = parseInt(padding);
        } else {
            padding = 0;
        }
        let style = document.getElementById('tnp-backend-css');
        style.innerText = '#tnpb-content.tnp-view-mobile { padding-left: ' + padding + 'px; padding-right: ' + padding + 'px; }';
    },

    load_template: function (id, ev) {
        ev.stopPropagation();
        ev.preventDefault();
        jQuery.ajax({
            type: "POST",
            url: ajaxurl,
            //async: false,
            data: {
                action: "tnpc_get_preset",
                id: id
            },
            success: function (res) {
                NewsletterComposer.set_content(res.data.content);
                NewsletterComposer.set_composer_options(res.data.globalOptions);
                NewsletterComposer.set_subject(res.data.subject);

                jQuery('#tnpb-settings-apply').click();

                NewsletterComposer.refresh_style();
                NewsletterComposer.init_builder();

                //jQuery(".tnpc-color").spectrum();

                jQuery.modal.close();
            },
        });
    },

    set_content: function (content) {
        jQuery('#tnpb-content').html(content ?? '');
    },

    get_content: function () {
        var el = jQuery('#tnpb-content').clone();

        el.find('.tnpc-row-actions').remove();
        el.find('.tnpc-row-action').remove();
        el.find('.tnpc-row').removeClass('ui-draggable');
        el.find('#tnpb-sortable-helper').remove();

        return btoa(encodeURIComponent(el.html()));
    },

    set_subject: function (subject) {
        jQuery('#options-subject').val(subject ?? '');
    },

    // Update the Composer options with the new ones, for example when the template is changed
    set_composer_options: function (options) {
        // It's an object
        for (const [key, value] of Object.entries(options)) {
            let el = jQuery('#options-options_composer_' + key);
            el.val(value);
            if (el.hasClass('tnpc-color')) {
                el.spectrum('set', value);
            }
        }
    },

    // Add the Composer options to the provided data (object or array) to be sent back, for example, to render a block
    add_composer_options: function (data) {
        let options = jQuery("#tnpb-settings :input").serializeArray();
        for (let i = 0; i < options.length; i++) {
            // options[options_composer_title_font_family] weird, isn't it?
            options[i].name = options[i].name.replace("options[options_composer_", "composer[");
            if (Array.isArray(data)) {
                data.push(options[i]);
            } else {
                //Inline edit data format is object not array
                data[options[i].name] = options[i].value;
            }
        }
    },

    // Get the current block options, including the composer options
    get_block_options: function () {
        var data = jQuery("#tnpc-block-options-form :input").serializeArray();
        this.add_composer_options(data);
        return data;
    },

    init_block_options_form: function () {
        jQuery("#tnpc-block-options-cancel").click(function (ev) {
            ev.preventDefault();
            ev.stopPropagation();

            NewsletterComposer.close_block_options();

            jQuery.post(ajaxurl, tnp_backup_block_options, function (response) {
                target.html(response);
                jQuery("#tnpc-block-options-form").html("");
            });
        });

        jQuery("#tnpc-block-options-save").on('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();

            // fix for Codemirror
            if (typeof templateEditor !== 'undefined') {
                templateEditor.save();
            }

            if (window.tinymce)
                window.tinymce.triggerSave();

            var data = NewsletterComposer.get_block_options();

            NewsletterComposer.close_block_options();

            jQuery.post(ajaxurl, data, function (response) {
                target.html(response);

                jQuery("#tnpc-block-options-form").html("");
            }).fail(function () {
                alert("Block rendering failed");
            });
        });

        jQuery('#tnpc-block-options-form').on('change', function (event) {
            let data = NewsletterComposer.get_block_options();

            jQuery.post(ajaxurl, data, function (response) {
                target.html(response);
                if (event.target.dataset.afterRendering === 'reload') {
                    tnp_container.find(".tnpc-row-edit").click();
                }
            }).fail(function () {
                alert("Block rendering failed");
            });

        });
    },

    // Initialize the drag and drop area and the contained block (on startup or template change)
    init_builder: function () {
        jQuery("#tnpb-content").sortable({
            revert: false,
            placeholder: "tnpb-placeholder",
            forcePlaceholderSize: true,
            opacity: 0.6,
            tolerance: "pointer",
            helper: function (e) {
                var helper = jQuery(document.getElementById("tnpb-sortable-helper")).clone();
                return helper;
            },
            update: function (ev, ui) {
                if (ui.item.attr("id") === "tnpb-draggable-helper") {
                    loading_row = jQuery('<div style="text-align: center; padding: 20px; background-color: #d4d5d6; color: #52BE7F;"><i class="fa fa-cog fa-2x fa-spin" /></div>');
                    ui.item.before(loading_row);
                    ui.item.remove();
                    var data = [
                        {"name": 'action', "value": 'tnpc_render'},
                        {"name": 'id', "value": ui.item.data("id")},
                        {"name": 'full', "value": 1},
                        {"name": "context_type", "value": tnp_context_type},
                        {"name": '_wpnonce', "value": tnp_nonce}
                    ];

                    NewsletterComposer.add_composer_options(data);

                    jQuery.post(ajaxurl, data, function (response) {
                        var new_row = jQuery(response);
                        loading_row.before(new_row);
                        loading_row.remove();
                        new_row.add_block_actions();
//                        new_row.on('click', function (ev) {
//                            let target = jQuery(ev.target);
//                            if (target.hasClass('tnpc-inline-editable')) {
//                                return;
//                            }
//                            jQuery(this).find(".tnpc-row-edit").click();
//                        });
                        if (new_row.hasClass('tnpc-row-block')) {
                            new_row.find(".tnpc-row-edit").click();
                        }
                    }).fail(function () {
                        alert("Block rendering failed.");
                        loading_row.remove();
                    });
                }
            }
        });

        jQuery(".tnpb-block-icon").draggable({
            connectToSortable: "#tnpb-content",

            helper: function (e) {
                var helper = jQuery(document.getElementById("tnpb-draggable-helper")).clone();
                // Do not uset .data() with jQuery
                helper.attr("data-id", e.currentTarget.dataset.id);
                helper.html(e.currentTarget.dataset.name);
                return helper;
            },
            revert: false,
            start: function () {
                if (jQuery('.tnpc-row').length) {
                } else {
                    jQuery('#tnpb-content').append('<div class="tnpc-drop-here">Drag&Drop blocks here!</div>');
                }
            },
            stop: function (event, ui) {
                jQuery('.tnpc-drop-here').remove();
            }
        });

        jQuery(".tnpc-row").add_block_actions();
    },

    init_composer_options_form: function () {
        jQuery('#tnpb-settings-apply').on('click', ev => {
            ev.preventDefault();
            ev.stopPropagation();

            var data = {
                'action': 'tnpc_regenerate_email',
                'content': NewsletterComposer.get_content(),
                '_wpnonce': tnp_nonce,
            };

            NewsletterComposer.add_composer_options(data);

            jQuery.post(ajaxurl, data, response => {
                if (response && response.success) {
                    jQuery('#tnpb-content').html(response.data.content);
                    NewsletterComposer.refresh_style();
                    NewsletterComposer.init_builder();
                }
                TNP.toast(response.data.message);
            });

        });
    },

    init_modals: function () {
        // Remove the last test results from the test modal
        jQuery('#test-newsletter-modal').on('modal:close', function (ev, modal) {
            jQuery('#test-newsletter-message').html('');
        });

        jQuery('#test-newsletter-modal').on('modal:open', (ev, modal) => {
            jQuery('#test-newsletter-message').html('');
        });
    },

    init_change_view: function () {
        jQuery('#tnpc-view-mode').on('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            if (NewsletterComposer.current_view === 'desktop') {
                NewsletterComposer.current_view = 'mobile';
                document.getElementById('tnpc-view-mode-icon').className = 'fas fa-mobile';
                jQuery('#tnpb-content').addClass('tnp-view-mobile');
            } else {
                NewsletterComposer.current_view = 'desktop';
                document.getElementById('tnpc-view-mode-icon').className = 'fas fa-desktop';
                jQuery('#tnpb-content').removeClass('tnp-view-mobile');
            }
        });
    },

    show_block_options: function () {
        jQuery("#tnpc-block-options").fadeIn(500);
        jQuery("#tnpc-block-options").css('display', 'flex');
    },

    close_block_options: function () {
        jQuery("#tnpc-block-options").fadeOut(500);
        jQuery("#tnpc-block-options-form").html('');
    },

    hide_block_options: function () {
        //jQuery("#tnpc-block-options").fadeOut(500);
        jQuery("#tnpc-block-options-form").html('');
    }

}

/**
 * @deprecated Kept for compatibility
 */
function tnpc_reload_options(e) {

}

function BuilderAreaHelper() {

    var _builderAreaEl = document.querySelector('#tnpb-main');
    var _overlayEl = document.createElement('div');
    _overlayEl.style.zIndex = 99999;
    _overlayEl.style.position = 'absolute';
    _overlayEl.style.top = 0;
    _overlayEl.style.left = 0;
    _overlayEl.style.width = '100%';
    _overlayEl.style.height = '100%';

    this.lock = function () {
        _builderAreaEl.appendChild(_overlayEl);
    }

    this.unlock = function () {
        _builderAreaEl.removeChild(_overlayEl);
    }

}

let builderAreaHelper = new BuilderAreaHelper();

function tnpc_test(to_email) {
    NewsletterComposer.save();
    NewsletterComposer.close_block_options();
    data = jQuery('#tnp-builder').closest('form').serializeArray();
    if (to_email) {
        data.push({
            name: 'to_email',
            value: '1'
        });
        // The modal library moves the div out of the form
        data.push({
            name: 'options[test_email]',
            value: document.getElementById('options-test_email').value
        });
    }
    data.push({
        name: 'action',
        value: 'tnpc_test'
    });
    jQuery.post(ajaxurl, data, function (response) {
        jQuery('#test-newsletter-message').html(response);
        jQuery('#test-newsletter-message').show();
    });

    return false;
}

function tnpb_open_tab(ev, tabName) {
    ev.preventDefault();
    ev.stopPropagation();
    let items = document.getElementsByClassName("tnpb-tab");
    for (let i = 0; i < items.length; i++) {
        items[i].style.display = "none";
    }

    items = document.getElementsByClassName("tnpb-tab-button");
    for (let i = 0; i < items.length; i++) {
        items[i].className = items[i].className.replace(" active", "");
    }

    document.getElementById(tabName).style.display = "block";
    ev.currentTarget.className += " active";
}

jQuery(document).ready(function () {
    'use strict'

    var TNPInlineEditor = (function () {

        var className = 'tnpc-inline-editable';
        var newInputName = 'new_name';
        var activeInlineElements = [];

        function init() {
            // find all inline editable elements
            jQuery('#tnpb-content').on('click', '.' + className, function (e) {
                e.preventDefault();
                //e.stopPropagation();
                removeAllActiveElements();

                var originalEl = jQuery(this).hide();
                var newEl = jQuery(getEditableComponent(this.innerText.trim(), this.dataset.id, this.dataset.type, originalEl)).insertAfter(this);

                activeInlineElements.push({'originalEl': originalEl, 'newEl': newEl});

                //Add submit event listener for newly created block
                jQuery('.tnpc-inline-editable-form-' + this.dataset.type + this.dataset.id).on('submit', function (e) {
                    submit(e, newEl, jQuery(originalEl));
                });

                //Add close event listener for newly created block
                jQuery('.tnpc-inline-editable-form-actions .tnpc-dismiss-' + this.dataset.type + this.dataset.id).on('click', function (e) {
                    removeAllActiveElements();
                });

            });

            // Close all created elements if clicked outside
            jQuery('#tnpb-content').on('click', function (e) {
                if (activeInlineElements.length > 0
                        && !jQuery(e.target).hasClass(className)
                        && jQuery(e.target).closest('.tnpc-inline-editable-container').length === 0) {
                    removeAllActiveElements();
                }
            });

        }

        function removeAllActiveElements() {
            activeInlineElements.forEach(function (obj) {
                obj.originalEl.show();

                obj.newEl.off();
                obj.newEl.remove();
            });

            activeInlineElements = []
        }

        function getEditableComponent(value, id, type, originalEl) {

            var element = '';

            //COPY FONT STYLE FROM ORIGINAL ELEMENT
            var fontFamily = originalEl.css('font-family');
            var fontSize = originalEl.css('font-size');
            var styleAttr = "style='font-family:" + fontFamily + ";font-size:" + fontSize + ";'";

            switch (type) {
                case 'text':
                {
                    element = "<textarea name='" + newInputName + "' class='" + className + "-textarea' rows='5' " + styleAttr + ">" + value + "</textarea>";
                    break;
                }
                case 'title':
                {
                    element = "<textarea name='" + newInputName + "' class='" + className + "-textarea' rows='2'" + styleAttr + ">" + value + "</textarea>";
                    break;
                }
            }

            var component = "<td>";
            component += "<form class='tnpc-inline-editable-form tnpc-inline-editable-form-" + type + id + "'>";
            component += "<input type='hidden' name='id' value='" + id + "'>";
            component += "<input type='hidden' name='type' value='" + type + "'>";
            component += "<input type='hidden' name='old_value' value='" + value + "'>";
            component += "<div class='tnpc-inline-editable-container'>";
            component += element;
            component += "<div class='tnpc-inline-editable-form-actions'>";
            component += "<button type='submit'><span class='dashicons dashicons-yes-alt' title='save'></span></button>";
            component += "<span class='dashicons dashicons-dismiss tnpc-dismiss-" + type + id + "' title='close'></span>";
            component += "</div>";
            component += "</div>";
            component += "</form>";
            component += "</td>";
            return component;
        }

        function submit(e, elementToDeleteAfterSubmit, elementToShow) {
            e.preventDefault();

            var id = elementToDeleteAfterSubmit.find('form input[name=id]').val();
            var type = elementToDeleteAfterSubmit.find('form input[name=type]').val();
            var newValue = elementToDeleteAfterSubmit.find('form [name="' + newInputName + '"]').val();

            ajax_render_block(elementToShow, type, id, newValue);

            elementToDeleteAfterSubmit.remove();
            elementToShow.show();

        }

        function ajax_render_block(inlineElement, type, postId, newContent) {

            var target = inlineElement.closest('.edit-block');
            var container = target.closest('table');
            var blockContent = target.children('.tnpc-block-content');

            if (container.hasClass('tnpc-row-block')) {
                var data = {
                    'action': 'tnpc_render',
                    'id': container.data('id'),
                    'b': container.data('id'),
                    'full': 1,
                    '_wpnonce': tnp_nonce,
                    'context_type': tnp_context_type,
                    'options': {
                        'inline_edits': [{
                                'type': type,
                                'post_id': postId,
                                'content': newContent
                            }]
                    },
                    'encoded_options': blockContent.data('json')
                };

                NewsletterComposer.add_composer_options(data);

                jQuery.post(ajaxurl, data, function (response) {
                    var new_row = jQuery(response);

                    container.before(new_row);
                    container.remove();

                    new_row.add_block_actions();

                    //Force reload options
                    if (new_row.hasClass('tnpc-row-block')) {
                        new_row.find(".tnpc-row-edit").click();
                    }

                }).fail(function () {
                    alert("Block rendering failed.");
                });

            }

        }

        return {init};
    })();

    TNPInlineEditor.init();

});

jQuery(function () {

    NewsletterComposer.init();

// ================================================================== //
// =================    SUBJECT LENGTH ICONS    ===================== //
// ================================================================== //

    (function subjectLengthIconsIIFE($) {
        var $subjectContainer = $('#tnpc-subject');
        var $subjectInput = $('#tnpc-subject input');
        var subjectCharCounterEl = null;

        $subjectInput.on('focusin', function (e) {
            $subjectContainer.find('img').fadeTo(400, 1);
        });

        $subjectInput.on('keyup', function (e) {
            setSubjectCharactersLenght(this.value.length);
        });

        $subjectInput.on('focusout', function (e) {
            $subjectContainer.find('img').fadeTo(300, 0);
        });

        function setSubjectCharactersLenght(length = 0) {

            if (length === 0 && subjectCharCounterEl !== null) {
                subjectCharCounterEl.remove();
                subjectCharCounterEl = null;
                return;
            }

            if (!subjectCharCounterEl) {
                subjectCharCounterEl = document.createElement("span");
                subjectCharCounterEl.style.position = 'absolute';
                subjectCharCounterEl.style.top = '-18px';
                subjectCharCounterEl.style.right = $subjectContainer[0].getBoundingClientRect().width - $subjectInput[0].getBoundingClientRect().width + 'px';
                subjectCharCounterEl.style.color = '#999';
                subjectCharCounterEl.style.fontSize = '0.8rem';
                $subjectContainer.find('div')[0].appendChild(subjectCharCounterEl);
            }

            const word = length === 1 ? 'character' : 'characters';
            subjectCharCounterEl.innerHTML = `${length} ${word}`;
        }

    })(jQuery);


});
