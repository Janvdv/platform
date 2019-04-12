define(function(require) {
    'use strict';

    var PageStateView;
    var $ = require('jquery');
    var _ = require('underscore');
    var base64 = require('base64');
    var routing = require('routing');
    var __ = require('orotranslation/js/translator');
    var mediator = require('oroui/js/mediator');
    var Modal = require('oroui/js/modal');
    var PageStateModel = require('oronavigation/js/app/models/page-state-model');
    var pageStateChecker = require('oronavigation/js/app/services/page-state-checker');
    var BaseView = require('oroui/js/app/views/base/view');

    PageStateView = BaseView.extend({
        listen: {
            'change:data model': '_saveModel',
            'change model': '_updateCache',

            'page:request mediator': 'onPageRequest',
            'page:update mediator': 'onPageUpdate',
            'page:afterChange mediator': 'afterPageChange',
            'page:afterPagePartChange mediator': 'afterPageChange',
            'page:beforeRefresh mediator': 'beforePageRefresh',
            'openLink:before mediator': 'beforePageChange'
        },

        /**
         * @inheritDoc
         */
        constructor: function PageStateView() {
            PageStateView.__super__.constructor.apply(this, arguments);
        },

        /**
         * @inheritDoc
         */
        initialize: function(options) {
            var confirmModal;
            this.model = new PageStateModel();

            this._initialState = null;
            this._resetChanges = false;

            confirmModal = new Modal({
                title: __('Refresh Confirmation'),
                content: __('Your local changes will be lost. Are you sure you want to refresh the page?'),
                okText: __('OK, got it.'),
                className: 'modal modal-primary',
                cancelText: __('Cancel'),
                disposeOnHidden: false
            });
            this.subview('confirmModal', confirmModal);

            $(window).on('beforeunload' + this.eventNamespace(), _.bind(this.onWindowUnload, this));

            this.isStateChanged = this.isStateChanged.bind(this);
            pageStateChecker.registerChecker(this.isStateChanged);

            PageStateView.__super__.initialize.call(this, options);
        },

        /**
         * @inheritDoc
         */
        dispose: function() {
            if (this.disposed) {
                return;
            }
            pageStateChecker.removeChecker(this.isStateChanged);
            $(window).off(this.eventNamespace());
            PageStateView.__super__.dispose.apply(this, arguments);
        },

        /**
         * Handle page's refresh action
         * if confirmation is required:
         *  - prepares deferred object
         *  - puts deferred object into refresh
         *
         * @param {Array} queue
         */
        beforePageRefresh: function(queue) {
            var deferred;
            var confirmModal;
            var self;
            if (!this.model.get('data')) {
                // data is not set, nothing to compare with
                return;
            }
            var preservedState = JSON.parse(this.model.get('data'));
            if (this._isStateChanged(preservedState)) {
                self = this;
                confirmModal = this.subview('confirmModal');
                deferred = $.Deferred();

                deferred.always(function() {
                    self.stopListening(confirmModal);
                });
                this.listenTo(confirmModal, 'ok', function() {
                    deferred.resolve({resetChanges: true});
                });
                this.listenTo(confirmModal, 'cancel', function() {
                    deferred.reject();
                });

                queue.push(deferred);
                confirmModal.open();
            }
        },

        /**
         * Returns if form state was changed
         *
         * @returns {Boolean}
         */
        isStateChanged: function() {
            return this._isStateChanged();
        },

        /**
         * Handles navigation action and shows confirm dialog
         * if page changes is not preserved and the state is changed from initial
         * (excludes cancel action)
         */
        beforePageChange: function(e) {
            var action = $(e.target).data('action');
            if (action !== 'cancel' && !this._isStateTraceRequired() && this._isStateChanged()) {
                e.prevented = !window.confirm(__('oro.ui.leave_page_with_unsaved_data_confirm'));
            }
        },

        /**
         * Clear page state timer and model on page request is started
         */
        onPageRequest: function() {
            this._initialState = null;
            this._resetChanges = false;
            this._switchOffTrace();
        },

        /**
         * Init page state on page updated
         * @param {Object} attributes
         * @param {Object} args
         */
        onPageUpdate: function(attributes, args) {
            var options;
            options = (args || {}).options;
            this._resetChanges = Boolean(options && options.resetChanges);
        },

        /**
         * Handles window unload event and shows confirm dialog
         * if page changes is not preserved and the state is changed from initial
         */
        onWindowUnload: function() {
            if (!this._isStateTraceRequired() && this._isStateChanged()) {
                return __('oro.ui.leave_page_with_unsaved_data_confirm');
            }
        },

        /**
         * Fetches model's attributes from cache on page changes is done
         */
        afterPageChange: function() {
            var options;

            if (this._hasForm()) {
                this._initialState = this._collectFormsData();
            }
            if (!this._hasForm() || !this._isStateTraceRequired()) {
                return;
            }

            if (this._resetChanges) {
                // delete cache if changes are discarded
                mediator.execute('pageCache:state:save', 'form', null);
                options = {initial: true};
            }

            this._switchOnTrace(options);
        },

        /**
         * Switch on/off form state trace
         */
        toggleStateTrace: function() {
            var switchOn = this._isStateTraceRequired();
            if (switchOn) {
                this._switchOnTrace({initial: true});
            } else {
                this._switchOffTrace();
            }
        },

        /**
         * Switch on form state trace
         * @param {Object=} options
         * @protected
         */
        _switchOnTrace: function(options) {
            var attributes;
            attributes = mediator.execute('pageCache:state:fetch', 'form');
            if (attributes && attributes.id) {
                this._initStateTracer(attributes, options);
            } else {
                this._loadState(options);
            }
        },

        /**
         * Switch off form state trace
         * @protected
         */
        _switchOffTrace: function() {
            this.$el.off('change.page-state');
            this.model.clear({silent: true});
        },

        /**
         * Initializes form changes trace
         *  - if attributes is not in a cache, loads data from server
         * @param {Object=} options
         * @protected
         */
        _loadState: function(options) {
            var self = this;
            var checkIdRoute = 'oro_api_get_pagestate_checkid';
            var pageStateRoutes = this.$el.find('#pagestate-routes');
            if (pageStateRoutes.data()) {
                this.model.postRoute = pageStateRoutes.data('pagestate-put-route');
                this.model.putRoute = pageStateRoutes.data('pagestate-put-route');
                checkIdRoute = pageStateRoutes.data('pagestate-checkid-route');
            }

            var url = routing.generate(checkIdRoute, {pageId: this._combinePageId()});
            $.get(url).done(function(data) {
                var attributes;
                attributes = {
                    pageId: data.pagestate.pageId || self._combinePageId(),
                    data: self._resetChanges ? '' : data.pagestate.data,
                    pagestate: data.pagestate
                };
                if (data.id) {
                    attributes.id = data.id;
                }
                self._initStateTracer(attributes, options);
            });
        },

        /**
         * Resets page state model, restores page forms and start tracing changes
         * @param {Object} attributes
         * @param {Object=} options
         * @protected
         */
        _initStateTracer: function(attributes, options) {
            var currentData;
            options = options || {};
            currentData = JSON.stringify(this._collectFormsData());
            if (!attributes.data || options.initial) {
                attributes.data = currentData;
            }
            this.model.set(attributes);
            if (attributes.data !== currentData) {
                this._restoreState();
            }
            this.$el.on('change.page-state', _.bind(this._collectState, this));
        },

        /**
         * Updates state in cache on model sync
         * @protected
         */
        _updateCache: function() {
            var attributes;
            attributes = {};
            _.extend(attributes, this.model.getAttributes());
            mediator.execute('pageCache:state:save', 'form', attributes);
        },

        /**
         * Defines if page has forms and state tracing is required
         * @returns {boolean}
         * @protected
         */
        _hasForm: function() {
            return Boolean(this.$('form[data-collect=true]').length);
        },

        /**
         * Handles model save
         * @protected
         */
        _saveModel: function() {
            // page state is the same -- nothing to save
            if (this.model.get('pagestate').data === this.model.get('data')) {
                return;
            }
            // @TODO why data duplication is required?
            this.model.save({
                pagestate: {
                    pageId: this.model.get('pageId'),
                    data: this.model.get('data')
                }
            });
        },

        /**
         * Collects data of page forms and update model if state is changed
         *  - collects data
         *  - updates model
         * @protected
         */
        _collectState: function() {
            var pageId = this._combinePageId();
            if (!pageId) {
                return;
            }

            var data = JSON.stringify(this._collectFormsData());

            if (data === this.model.get('data')) {
                return;
            }

            this.model.set({
                pageId: pageId,
                data: data
            });
        },

        /**
         * Goes through the form and collects data
         * @returns {Array}
         * @protected
         */
        _collectFormsData: function() {
            var data;
            data = [];
            $('form[data-collect=true]').each(function(index, el) {
                var items = $(el)
                    .find('input, textarea, select')
                    .not(':input[type=button],   :input[type=submit], :input[type=reset], ' +
                         ':input[type=password], :input[type=file],   :input[name$="[_token]"], ' +
                         '.select2[type=hidden]');

                data[index] = items.serializeArray();

                // collect select2 selected data
                items = $(el).find('.select2[type=hidden], .select2[type=select]');
                _.each(items, function(item) {
                    var $item = $(item);
                    var selectedData = $item.inputWidget('data');
                    var itemData = {name: item.name, value: $item.val()};

                    if (!_.isEmpty(selectedData)) {
                        itemData.selectedData = selectedData;
                    }

                    data[index].push(itemData);
                });
            });
            return data;
        },

        /**
         * Reads data from model and restores page forms
         * @protected
         */
        _restoreState: function() {
            var data;
            data = this.model.get('data');

            if (data) {
                this._restoreForms(JSON.parse(data));
                mediator.trigger('pagestate_restored');
            }
        },

        /**
         * Updates form from data
         * @param {Array} data
         * @protected
         */
        _restoreForms: function(data) {
            $.each(data, function(index, el) {
                var form = $('form[data-collect=true]').eq(index);

                $.each(el, function(i, input) {
                    var element = form.find('[name="' + input.name + '"]');
                    switch (element.prop('type')) {
                        case 'checkbox':
                            element.filter('[value="' + input.value + '"]').prop('checked', true);
                            break;
                        case 'select-multiple':
                            element
                                .find('option').prop('selected', false).end()
                                .find('option[value="' + input.value + '"]').prop('selected', true);
                            break;
                        default:
                            if (input.selectedData) {
                                element.data('selected-data', input.selectedData);
                            }
                            if (input.value !== element.val()) {
                                element.val(input.value).trigger('change');
                            }
                    }
                });
            });
        },

        /**
         * Combines pageId
         * @returns {string}
         * @protected
         */
        _combinePageId: function() {
            return base64.encode(this._getCurrentURL());
        },

        /**
         * Parses URL for current page
         * @returns {Object}
         * @protected
         */
        _getCurrentURL: function() {
            var url = mediator.execute('currentUrl');
            url = mediator.execute('normalizeUrl', url);

            return url;
        },

        /**
         * Allows to overload _isStateTraceRequired stub-method
         *
         * @param {Function} callback
         */
        setStateTraceRequiredChecker: function(callback) {
            this._isStateTraceRequired = callback;
        },

        /**
         * Defines if page is in cache and state trace is required
         * (it is stub-method and can be overloaded)
         * @protected
         */
        _isStateTraceRequired: function() {
            return false;
        },

        /**
         * Check if passed or current state is different from initial sate
         *
         * @param {Array=} state if not passed collects current state
         * @returns {boolean}
         * @protected
         */
        _isStateChanged: function(state) {
            state = state || this._collectFormsData();
            return this._initialState !== null && this._isDifferentFromInitialState(state);
        },

        /**
         * Check if passed state is different from initial state
         * compares just name-value pairs
         * (comparison of JSON strings is not in use, because field items can contain extra-data)
         *
         * @param {Array} state
         * @returns {boolean}
         * @protected
         */
        _isDifferentFromInitialState: function(state) {
            var initialState = this._initialState;

            if (_.isArray(state)) {
                $.each(state, function(index, item) {
                    if (_.isArray(item)) {
                        item = $.grep(item, function(field) {
                            return _.isObject(field) && field.name.indexOf('temp-validation-name-') === -1;
                        });
                        state[index] = item;
                    }
                });
            }

            var isSame = initialState && _.every(initialState, function(form, i) {
                return _.isArray(state[i]) && _.every(form, function(field, j) {
                    return _.isObject(state[i][j]) &&
                        state[i][j].name === field.name && state[i][j].value === field.value;
                });
            });
            return !isSame;
        }
    });

    return PageStateView;
});
