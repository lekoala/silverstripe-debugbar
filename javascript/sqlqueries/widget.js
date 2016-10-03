(function ($) {

    var csscls = PhpDebugBar.utils.makecsscls('phpdebugbar-widgets-');

    /**
     * Widget for the displaying sql queries
     *
     * Options:
     *  - data
     */
    var SQLQueriesWidget = PhpDebugBar.Widgets.SQLQueriesWidget = PhpDebugBar.Widget.extend({
        className: csscls('sqlqueries'),
        onFilterClick: function (el, cls, reverse) {
            $(el).toggleClass(csscls('excluded'));

            var excludedLabels = [];
            this.$toolbar.find(csscls('.filter') + csscls('.excluded')).each(function () {
                excludedLabels.push(this.rel);
            });

            if (typeof (reverse) != 'undefined' && reverse) {
                this.$list.$el.find("li:not(." + csscls(cls) + ')').toggle();
            } else {
                this.$list.$el.find("li:not(." + csscls(cls) + ')').toggle();
            }

            this.set('exclude', excludedLabels);
        },
        render: function () {
            this.$status = $('<div />').addClass(csscls('status')).appendTo(this.$el);

            this.$toolbar = $('<div></div>').addClass(csscls('toolbar')).appendTo(this.$el);

            var filters = [], self = this;

            this.$list = new PhpDebugBar.Widgets.ListWidget({itemRenderer: function (li, stmt) {
                    $('<code />').addClass(csscls('sql')).html(PhpDebugBar.Widgets.highlight(stmt.sql, 'sql')).appendTo(li);
                    if (stmt.duration_str) {
                        $('<span title="Duration" />').addClass(csscls('duration')).text(stmt.duration_str).appendTo(li);
                    }
                    if (stmt.memory_str) {
                        $('<span title="Memory usage" />').addClass(csscls('memory')).text(stmt.memory_str).appendTo(li);
                    }
                    if (typeof (stmt.row_count) != 'undefined') {
                        $('<span title="Row count" />').addClass(csscls('row-count')).text(stmt.row_count).appendTo(li);
                    }
                    if (typeof (stmt.source) != 'undefined' && stmt.source) {
                        $('<span title="Source" />').addClass(csscls('source')).text(stmt.source).appendTo(li);
                    }
                    if (typeof (stmt.stmt_id) != 'undefined' && stmt.stmt_id) {
                        $('<span title="Prepared statement ID" />').addClass(csscls('stmt-id')).text(stmt.stmt_id).appendTo(li);
                    }
                    if (stmt.database) {
                        $('<span title="Database" />').addClass(csscls('database')).text(stmt.database).appendTo(li);
                        li.addClass(csscls('database' + stmt.database));
                        if ($.inArray(stmt.database, filters) == -1) {
                            filters.push(stmt.database);
                        }
                    }
                    if (typeof (stmt.is_success) != 'undefined' && !stmt.is_success) {
                        li.addClass(csscls('error'));
                        li.append($('<span />').addClass(csscls('error')).text("[" + stmt.error_code + "] " + stmt.error_message));
                    }
                    if (stmt.params) {
                        var table;
                        switch ($.type(stmt.params)) {
                            case 'string':
                                table = $('<div class="' + csscls('queryinfo') + '">' + stmt.params + '</div>').appendTo(li);
                                break;
                            case 'array':
                                if ($.isEmptyObject(stmt.params)) {
                                    break;
                                }
                                table = $('<table><tr><th colspan="2">Params</th></tr></table>').addClass(csscls('params')).appendTo(li);
                                for (var key in stmt.params) {
                                    if (typeof stmt.params[key] !== 'function') {
                                        table.append('<tr><td class="' + csscls('name') + '">' + key + '</td><td class="' + csscls('value') +
                                                '">' + stmt.params[key] + '</td></tr>');
                                    }
                                }
                                break;
                        }
                        if (table) {
                            li.css('cursor', 'pointer').click(function () {
                                if (table.is(':visible')) {
                                    table.hide();
                                } else {
                                    table.show();
                                }
                            });
                        }
                    }
                }
            });

            this.$list.$el.appendTo(this.$el);

            this.bindAttr('data', function (data) {
                this.$list.set('data', data.statements);
                this.$status.empty();

                // Search for duplicate statements.
                for (var sql = {}, duplicate = 0, i = 0; i < data.statements.length; i++) {
                    var stmt = data.statements[i].sql;
                    if (data.statements[i].params && !$.isEmptyObject(data.statements[i].params)) {
                        stmt += ' {' + $.param(data.statements[i].params, false) + '}';
                    }
                    sql[stmt] = sql[stmt] || {keys: []};
                    sql[stmt].keys.push(i);
                }
                // Add classes to all duplicate SQL statements.
                for (var stmt in sql) {
                    if (sql[stmt].keys.length > 1) {
                        duplicate++;
                        for (var i = 0; i < sql[stmt].keys.length; i++) {
                            // Add a visual badge
                            var $badge = $('<span class="' + csscls('badge') + '">' + duplicate + '</span>');
                            this.$list.$el.find('.' + csscls('list-item')).eq(sql[stmt].keys[i])
                                    .addClass(csscls('sql-duplicate')).addClass(csscls('sql-duplicate-' + duplicate)).append($badge);
                        }
                    }
                }

                var t = $('<span />').text(data.nb_statements + " statements were executed").appendTo(this.$status);
                if (data.nb_failed_statements) {
                    t.append(", " + data.nb_failed_statements + " of which failed");
                }
                if (duplicate) {
                    t.append(", " + duplicate + " of which were duplicated");
                }
                if (data.accumulated_duration_str) {
                    this.$status.append($('<span title="Accumulated duration" />').addClass(csscls('duration')).text(data.accumulated_duration_str));
                }
                if (data.memory_usage_str) {
                    this.$status.append($('<span title="Memory usage" />').addClass(csscls('memory')).text(data.memory_usage_str));
                }

                // We have multiple database or duplicates, show filters
                if (filters.length > 1 || duplicate) {
                    self.$toolbar.empty();

                    if (filters.length > 1) {
                        $.each(filters, function (index, value) {
                            $('<a />')
                                    .addClass(csscls('filter'))
                                    .text('db:' + value)
                                    .attr('rel', value)
                                    .on('click', function () {
                                        self.onFilterClick(this, 'database-' + value);
                                    })
                                    .appendTo(self.$toolbar);
                        });
                    } else {
                        filters = [];
                    }

                    if (duplicate) {
                        $('<a />')
                                .addClass(csscls('filter'))
                                .addClass(csscls('excluded'))
                                .text('only duplicated queries')
                                .attr('rel', '_duplicated')
                                .on('click', function () {
                                    self.onFilterClick(this, 'sql-duplicate', true);
                                })
                                .appendTo(self.$toolbar);
                    }
                    self.$toolbar.show();
                    self.$list.$el.css("margin-bottom", "24px");
                }
            });
        }

    });

})(PhpDebugBar.$);
