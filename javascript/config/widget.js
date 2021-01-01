(function ($) {
    var csscls = PhpDebugBar.utils.makecsscls('phpdebugbar-widgets-');

    /**
     * Widget for displaying a formatted view of config calls and results for a request
     */
    var ConfigWidget = PhpDebugBar.Widgets.ConfigWidget = PhpDebugBar.Widget.extend({
        className: csscls('config'),
        tagName: 'ul',

        render: function() {
            this.bindAttr(['data'], function() {
                this.$el.empty();
                if (!this.has('data')) {
                    return;
                }

                var self = this;
                $.each(this.get('data'), function(className, args) {
                    var listItem = $('<li />').addClass(csscls('item'));

                    $('<a />')
                        .addClass(csscls('classname'))
                        .html(className)
                        .appendTo(listItem)
                        .on('click', function(e) {
                            e.preventDefault();
                            $(this).parent('li').children('ul').toggle();
                        });

                    var keysList = $('<ul />')
                        .addClass(csscls('keyslist'))
                        .attr('style', 'display: none')
                        .appendTo(listItem);

                    $.each(args, function(key, result) {
                        var heading = key;

                        if (result === null) {
                            return true;
                        }

                        if (parseInt(result.calls) > 1) {
                            heading += ' <span class="phpdebugbar-badge">' + result.calls + '</span>';
                        }

                        var container = $('<div />').addClass(csscls('container'));

                        $('<h4 />')
                            .addClass(csscls('key'))
                            .html(heading)
                            .appendTo(container);

                        $('<pre />')
                            .addClass(csscls('value'))
                            .html('<code>' + JSON.stringify(result.result, null, 2) + '</code>')
                            .appendTo(container);

                        container.appendTo(keysList);
                    });

                    listItem.appendTo(self.$el);
                });
            });
        }
    });
})(PhpDebugBar.$);
