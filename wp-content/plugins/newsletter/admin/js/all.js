const TNPModal = function (options) {
    'use strict'

    const _options = {
        title: '',
        content: '',
        contentSelector: '',
        showClose: true,
        onClose: null,
        closeWhenClickOutside: true,
        confirmText: 'CONFIRM',
        confirmClassName: 'button',
        showConfirm: false,
        onConfirm: null,
        clickConfirmOnPressEnter: false,
        style: null,
        ...options
    };

    let _modalElement = null;
    let _modalContainer = null;
    let _closeElement = null;
    let _contentElement = null;
    let _isClosing = false;

    const open = () => {
        if (_modalElement === null) {
            //render element
            _render();
        }
        return _contentElement;
    }

    const close = () => {

        if (!_isClosing) {
            _modalElement.addEventListener('animationend', function () {
                document.body.removeChild(_modalElement);
                destroyDOMElements();
                _isClosing = false;
            });

            _modalContainer.className = _modalContainer.className + ' on-close';
            _modalElement.className = _modalElement.className + ' on-close';

            if (_options.onClose) {
                _options.onClose();
            }
            _isClosing = true;
        }

    }

    const destroyDOMElements = () => {
        if (_contentElement) {
            _contentElement.style.display = 'none';
            document.body.appendChild(_contentElement);
        }
        _modalElement = null;
        _modalContainer = null;
        _closeElement = null;
        _contentElement = null;
    }

    const onConfirm = () => {

        if (_options.onConfirm) {
            _options.onConfirm();
        }

        close();
    }

    const _addTitle = (title) => {
        const titleElement = document.createElement('h2');
        titleElement.className = 'tnp-modal-title';
        titleElement.innerText = title;

        _modalContainer.appendChild(titleElement);
    }

    const _addCloseButton = () => {
        const closeEl = document.createElement('div');
        closeEl.className = 'tnp-modal-close';
        closeEl.innerText = '×';

        _modalContainer.appendChild(closeEl);

        closeEl.addEventListener('click', function (e) {
            e.stopPropagation();
            close();
        });
    }

    const _render = () => {

        _modalContainer = document.createElement('div');
        _modalContainer.className = 'tnp-modal-container';

        if (_options.title && _options.title.length > 0) {

            _addTitle(_options.title);

        }

        if (_options.content && _options.content.length > 0) {

            _contentElement = document.createElement('div');
            _contentElement.className = 'tnp-modal-content';
            _contentElement.innerHTML = _options.content;
            _modalContainer.appendChild(_contentElement);

        } else if (_options.contentSelector && _options.contentSelector.length > 0) {

            _contentElement = document.querySelector(_options.contentSelector);
            _contentElement.style.display = _contentElement.style.display === 'none' ? 'block' : _contentElement.style.display;
            _modalContainer.appendChild(_contentElement);

        } else {

            _contentElement = document.createElement('div');
            _contentElement.className = 'tnp-modal-content';
            _modalContainer.appendChild(_contentElement);

        }

        if (_options.showClose) {
            _addCloseButton();
        }

        if (_options.showConfirm) {

            const confirmContainerEl = document.createElement('div');
            confirmContainerEl.className = 'tnp-modal-confirm';

            const confirmEl = document.createElement('button');
            confirmEl.className = _options.confirmClassName || 'button-secondary';
            confirmEl.innerText = _options.confirmText || 'CONFIRM';

            confirmEl.addEventListener('click', onConfirm);

            if (_options.clickConfirmOnPressEnter) {
                document.addEventListener('keyup', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        confirmEl.click();
                    }
                })
            }

            confirmContainerEl.appendChild(confirmEl);
            _modalContainer.appendChild(confirmContainerEl);

        }

        if (_options.style) {
            for (const _styleProperty in _options.style) {
                if (_modalContainer.style && typeof (_modalContainer.style[_styleProperty]) !== "undefined") {
                    _modalContainer.style[_styleProperty] = _options.style[_styleProperty];
                }
            }
        }

        if (_options.backgroundColor) {
            _modalContainer.style.backgroundColor = _options.backgroundColor;
        }

        if (_options.height) {
            _modalContainer.style.height = _options.backgroundColor;
        }


        _modalElement = document.createElement('div');
        _modalElement.className = 'tnp-modal open';

        if (_options.closeWhenClickOutside) {
            //Close modal if clicked outside modal
            _modalElement.addEventListener('click', function (event) {
                if (!event.target.closest('.' + _modalContainer.className)) {
                    close();
                }
            });
        }

        _modalElement.appendChild(_modalContainer);
        document.body.appendChild(_modalElement);

    }

    if (_options.triggerSelector && _options.triggerSelector.length > 0) {
        const _triggerElement = document.querySelector(_options.triggerSelector);
        _triggerElement.addEventListener('click', open);
    }

    return {
        open,
        close
    }

};

jQuery(function() {
window.TNPModal = TNPModal;
});

const TNPModal2 = (function () {
    'use strict'

    var modalClass = '.tnp-modal2';
    var dataModalTriggerSelector = 'data-tnp-modal-target';
    var dataCloseModalTriggerSelector = 'data-tnp-modal-close';

    class TNPModalx {

        constructor() {

            var self = this;
            var triggers = document.querySelectorAll(`[${dataModalTriggerSelector}]`);

            //Inizializzo i trigger di apertura delle modali
            self._forEach(triggers, function (index, item) {

                var modalTriggerSelector = item.getAttribute(dataModalTriggerSelector);

                item.addEventListener('click', function (e) {
                    self.open(modalTriggerSelector);
                });

            });

            //Inizializzo i trigger di chiusura delle modali
            var closeModalTriggersEl = document.querySelectorAll(`[${dataCloseModalTriggerSelector}]`);
            self._forEach(closeModalTriggersEl, function (index, closeTriggerEl) {
                closeTriggerEl.addEventListener('click', function (e) {
                    self._closeModalElement(e.target.closest(modalClass));
                });
            });

        }

        open(modalSelector) {
            var self = this;
            var modalEl = document.querySelector(modalSelector);

            const showModalEvent = new Event('show.tnp.modal');
            modalEl.dispatchEvent(showModalEvent);

            modalEl.classList.add('open');

            modalEl.addEventListener('click', function (e) {
                if (!e.target.closest('.tnp-modal2__content')) {
                    self._closeModalElement(modalEl);
                }
            });
        }

        close(modalSelector) {
            var modalEl = document.querySelector(modalSelector);
            this._closeModalElement(modalEl);
        }

        _closeModalElement(modal) {
            const hideModalEvent = new Event('hide.tnp.modal');
            modal.dispatchEvent(hideModalEvent);

            modal.classList.add('on-close');

            modal.addEventListener('animationend', function () {
                modal.classList.remove('open');
                modal.classList.remove('on-close');

                const hiddenModalEvent = new Event('hidden.tnp.modal');
                modal.dispatchEvent(hiddenModalEvent);
            }, {once: true});
        }

        _forEach(array, callback, scope) {
            for (var i = 0; i < array.length; i++) {
                callback.call(scope, i, array[i]);
            }
        }
        ;
    }

    return new TNPModalx();
});

jQuery(function() {
window.TNPModal2 = TNPModal2();
});
jQuery.cookie = function (name, value, options) {
    if (typeof value != 'undefined') { // name and value given, set cookie
        options = options || {};
        if (value === null) {
            value = '';
            options.expires = -1;
        }
        var expires = '';
        if (options.expires && (typeof options.expires == 'number' || options.expires.toUTCString)) {
            var date;
            if (typeof options.expires == 'number') {
                date = new Date();
                date.setTime(date.getTime() + (options.expires * 24 * 60 * 60 * 1000));
            } else {
                date = options.expires;
            }
            expires = '; expires=' + date.toUTCString(); // use expires attribute, max-age is not supported by IE
        }
        var path = options.path ? '; path=' + (options.path) : '';
        var domain = options.domain ? '; domain=' + (options.domain) : '';
        var secure = options.secure ? '; secure' : '';
        document.cookie = [name, '=', encodeURIComponent(value), expires, path, domain, secure].join('');
    } else { // only name given, get cookie
        var cookieValue = null;
        if (document.cookie && document.cookie != '') {
            var cookies = document.cookie.split(';');
            for (var i = 0; i < cookies.length; i++) {
                var cookie = jQuery.trim(cookies[i]);
                // Does this cookie string begin with the name we want?
                if (cookie.substring(0, name.length + 1) == (name + '=')) {
                    cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
                    break;
                }
            }
        }
        return cookieValue;
    }
};

jQuery(function ($) {
    $('.tnpc-default-text').click(function (e) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    });
    tnp_refresh_binds();
});

function tnp_refresh_binds() {
    jQuery('[data-bind]').each(function () {
        var id = this.dataset.bind;
        var v;
        if (id.substring(0, 1) === '!') {
            v = !document.getElementById(id.substring(1)).checked;
        } else {
            v = document.getElementById(this.dataset.bind).checked;
        }
        jQuery(this).toggle(v);
    });
}

function tnp_toggle_schedule() {
    jQuery("#tnp-schedule-button").toggle();
    jQuery("#tnp-schedule").toggle();
}

function tnp_select_toggle(s, t) {
    if (s.value == 1) {
        jQuery("#options-" + t).show();
    } else {
        jQuery("#options-" + t).hide();
    }
}

/*
 * Used by the date field of NewsletterControls
 */
function tnp_date_onchange(field) {
    let id = field.id.substring(0, field.id.lastIndexOf('_'));
    let base_field = document.getElementById('options-' + id);
    let year = document.getElementById(id + '_year');
    let month = document.getElementById(id + '_month');
    let day = document.getElementById(id + '_day');
    if (year.value === '' || month.value === '' || day.value === '') {
        base_field.value = 0;
    } else {
        base_field.value = new Date(year.value, month.value, day.value, 12, 0, 0).getTime() / 1000;
    }
    //this.form.elements['options[" . esc_attr($name) . "]'].value = new Date(document.getElementById('" . esc_attr($name) . "_year').value, document.getElementById('" . esc_attr($name) . "_month').value, document.getElementById('" . esc_attr($name) . "_day').value, 12, 0, 0).getTime()/1000";
}

/**
 * Initialize the color pickers (is invoked on document load and on AJAX forms load in the composer.
 * https://seballot.github.io/spectrum/
 */
function tnp_controls_init(config = {}) {
    NewsletterControls.initialized = true;
    jQuery(".tnpc-color").spectrum({
        type: 'color',
        allowEmpty: true,
        showAlpha: false,
        showInput: true,
        preferredFormat: 'hex'
    });

    jQuery("textarea.dynamic").focus(function () {
        jQuery("textarea.dynamic").css("height", "50px");
        jQuery(this).css("height", "400px");
    });

    jQuery(".tnp-accordion").accordion({collapsible: true, heightStyle: "content"});
    if (!config.nested) {
        tabs = jQuery("#tabs").tabs({
            active: config.tab,
            activate: function (event, ui) {
                jQuery.cookie(config.tab_name, ui.newTab.index(), {expires: 1});
            }
        });
        jQuery(".tnp-tabs").tabs({
            active: config.tab,
            activate: function (event, ui) {
                jQuery.cookie(config.tab_name, ui.newTab.index(), {expires: 1});
            }
        });
}
}

function tnp_fields_media_mini_select(el) {
    event.preventDefault();

    let name = jQuery(el).data("name");

    let tnp_uploader = wp.media({
        title: "Select an image",
        button: {
            text: "Select"
        },
        multiple: false
    }).on("select", function () {
        let media = tnp_uploader.state().get("selection").first();
        let $field = jQuery("#" + name + "_id");
        $field.val(media.id);


        var img_url = media.attributes.url;
        if (typeof media.attributes.sizes.thumbnail !== "undefined")
            img_url = media.attributes.sizes.thumbnail.url;
        document.getElementById(name + "_img").src = img_url;
        $field.trigger("change");
    }).open();
}

function tnp_fields_url_select(el) {
    event.preventDefault();

    let field_id = jQuery(el).data("field");

    let tnp_uploader = wp.media({
        title: "Select an image",
        button: {
            text: "Select"
        },
        multiple: false
    }).on("select", function () {
        let media = tnp_uploader.state().get("selection").first();
        let $field = jQuery("#" + field_id);
        $field.val(media.attributes.url);
        $field.trigger("change");
    }).open();
}

function tnp_fields_media_mini_remove(name) {
    event.preventDefault();
    event.stopPropagation();
    let $field = jQuery("#" + name + "_id");
    $field.val("");
    $field.trigger("change");
    document.getElementById(name + "_img").src = "";
}

function tnp_lists_toggle(e) {
    jQuery('#' + e.id + '-notes > div').hide();
    jQuery('#' + e.id + '-notes .list_' + e.value).show();
}

function newsletter_media(name) {
    var tnp_uploader = wp.media({
        title: "Select an image",
        button: {
            text: "Select"
        },
        multiple: false
    }).on("select", function () {
        var media = tnp_uploader.state().get("selection").first();
        document.getElementById(name + "_id").value = media.id;
        if (media.attributes.url.substring(0, 0) == "/") {
            media.attributes.url = NewsletterControls.site_url + media.attributes.url;
        }
        document.getElementById(name + "_url").value = media.attributes.url;

        var img_url = media.attributes.url;
        if (typeof media.attributes.sizes.medium !== "undefined")
            img_url = media.attributes.sizes.medium.url;
        if (img_url.substring(0, 0) == "/") {
            img_url = NewsletterControls.site_url + img_url;
        }
        document.getElementById(name + "_img").src = img_url;
        var alt = document.getElementById("options-" + name + "_alt");
        if (alt) {
            if (media.attributes.alt) {
                alt.value = media.attributes.alt;
            } else {
                alt.value = media.attributes.title;
            }
        }
        jQuery("#" + name + "_id").trigger("change");
    }).open();
}

function newsletter_media_remove(name) {
    if (confirm("Are you sure?")) {
        document.getElementById(name + "_id").value = "";
        document.getElementById(name + "_url").value = "";
        document.getElementById(name + "_img").src = NewsletterControls.newsletter_url + '/admin/images/nomedia.png';
        var alt = document.getElementById("options-" + name + "_alt");
        if (alt) {
            alt.value = "";
        }
    }
}

function newsletter_textarea_preview(id, header, footer) {
    var d = document.getElementById(id + "-iframe").contentWindow.document;
    d.open();
    if (templateEditor) {
        d.write(templateEditor.getValue());
    } else {
        d.write(header + document.getElementById(id).value + footer);
    }
    d.close();

    var d = document.getElementById(id + "-iframe-phone").contentWindow.document;
    d.open();
    if (templateEditor) {
        d.write(templateEditor.getValue());
    } else {
        d.write(header + document.getElementById(id).value + footer);
    }
    d.close();
    //jQuery("#" + id + "-iframe-phone").toggle();
    jQuery("#" + id + "-preview").toggle();
}
function tnp_select_images(state) {
    if (!state.id) {
        return state.text;
    }
    var $state = jQuery("<span class=\"tnp-select2-option\"><img style=\"height: 20px!important; position: relative; top: 5px\" src=\"" + state.element.getAttribute("image") + "\"> " + state.text + "</span>");
    return $state;
}
function tnp_select_images_selection(state) {
    if (!state.id) {
        return state.text;
    }
    var $state = jQuery("<span class=\"tnp-select2-option\"><img style=\"height: 20px!important; position: relative; top: 5px\" src=\"" + state.element.getAttribute("image") + "\"> " + state.text + "</span>");
    return $state;
}

const TNP = {
    // Fields that control showing and hiding of other elements
    showable_controllers: [],

    toast: function (message) {
        Toastify({
            text: message,
            duration: 2000,
            //destination: "https://github.com/apvarun/toastify-js",
            //newWindow: true,
            //close: true,
            offset: {
                x: 0,
                y: '3em'
            },
            gravity: "top", // `top` or `bottom`
            position: "center", // `left`, `center` or `right`
            stopOnFocus: true, // Prevents dismissing of toast on hover
            style: {
                background: "linear-gradient(to right, var(--tnp-green), var(--tnp-green-dark))",
                'min-width': '200px',
                'text-align': 'center',
            },
            //onClick: function () {} // Callback after click
        }).showToast();
    },

    init_showables: function () {
        document.querySelectorAll('[data-tnpshow]').forEach(el => {
            let parts = el.dataset.tnpshow.split(/([=><])/);
            let controller = document.getElementById('options-' + parts[0]);
            TNP.process_showable(el, controller, parts[1], parts[2]);
            if (!TNP.showable_controllers.includes(controller.id)) {
                TNP.showable_controllers.push(controller.id);
                jQuery(controller).on('change', TNP.process_showables);
            }
        });
    },

    process_showables: function () {
        document.querySelectorAll('[data-tnpshow]').forEach(el => {
            let parts = el.dataset.tnpshow.split(/([=><])/);
            let controller = document.getElementById('options-' + parts[0]);
            TNP.process_showable(el, controller, parts[1], parts[2]);
        });
    },

    process_showable: function (el, controller, symbol, value) {
        if (!controller) {
            return;
        }
        cvalue = controller.value;
        if (cvalue === '')
            cvalue = '0'; // Patch for selects with the first entry set to an empty key
        if (controller.type === 'checkbox') {
            if (controller.checked) {
                jQuery(el).toggle(value == '1');
            } else {
                jQuery(el).toggle(value == '0');
            }
        } else {
            switch (symbol) {
                case '=':
                    jQuery(el).toggle(cvalue == value);
                    break;
                case '<':
                    jQuery(el).toggle(!isNaN(cvalue) && parseInt(cvalue) < parseInt(value));
                    break;
                case '>':
                    jQuery(el).toggle(!isNaN(cvalue) && parseInt(cvalue) > parseInt(value));
                    break;
            }
        }
    }
}

jQuery(function ($) {
    // jQuery modal library
    $.modal.defaults.fadeDuration = 250;
    TNP.init_showables();
});
