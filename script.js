(function ($) {
    $(document).ready(function () {

        try {
            var page = load_more.paged,
                can_load = true,
                load_more_button = $('#load-more');
        }
        catch (err) {
            console.log(err);
        }

        $('.filter-switch').click(function (e) {
            $(this).next('.filter-item').toggleClass('open');
            e.preventDefault();
            return false;
        });

        $(document).on('click', '#load-more', function (e) {
            if (can_load && page != last_page) {
                can_load = false;
                var main_container = $("#filter-search"),
                    orderby = $('.filters #orderby').val(),
                    fields = {
                        filter: main_container.serialize(),
                        action: 'seo_filter_woocommerce_load_more',
                        query: load_more,
                        page: page
                    },
                    new_url = default_link + "filter/",
                    add_filter = false,
                    new_big_part = [];
                main_container.find('input.name-filter').each(function () {
                    var filter_element = main_container.find('input[name="filter[' + $(this).val() + '][]"]:checked');
                    if (filter_element.length > 0) {
                        var new_part = [];
                        $.each(filter_element, function () {
                            new_part.push($(this).val());
                        });
                        new_big_part.push($(this).val() + '/' + new_part.join('-'));
                        add_filter = true;
                    }
                });
                new_url += new_big_part.join('/');
                new_url += '/';
                if (!add_filter)
                    new_url = default_link;
                if (orderby != 'default') {
                    fields.orderby = orderby;
                    new_url += 'orderby/' + orderby + '/';
                }
                $.post('/wp-admin/admin-ajax.php', fields, function (res) {
                    if (res.success) {
                        page++;
                        $('ul.products').append($(res.data['items']).fadeIn());
                        if (page == last_page) {
                            load_more_button.hide();
                        }
                        else
                            load_more_button.attr('href', new_url + 'page/' + (page + 1) + '/');
                    }
                    can_load = true;
                }).fail(function (xhr, textStatus, e) {
                    console.log(xhr.responseText);
                    can_load = true;
                });
                if (load_more_button.attr('href') != window.location) {
                    window.history.pushState(null, null, load_more_button.attr('href'));
                }
            }
            return false;
        });

        $(document).on('click', '#filter-search input', function () {
            if (can_load) {
                can_load = false;
                $(this).closest('li').toggleClass('active').find('.count').hide();
                create_url();
            }
        });

        $(document).on('change', '.filters #orderby', function () {
            if (can_load) {
                can_load = false;
                create_url();
            }
        });

        function create_url() {
            var main_container = $("#filter-search"),
                orderby = $('.filters #orderby').val(),
                fields = {
                    filter: main_container.serialize(),
                    action: 'do_filter_woocommerce',
                    query: load_more
                },
                new_url = default_link + "filter/",
                add_filter = false,
                new_big_part = [];
            main_container.find('input.name-filter').each(function () {
                var filter_element = main_container.find('input[name="filter[' + $(this).val() + '][]"]:checked');
                if (filter_element.length > 0) {
                    var new_part = [];
                    $.each(filter_element, function () {
                        new_part.push($(this).val());
                    });
                    new_big_part.push($(this).val() + '/' + new_part.join('-'));
                    add_filter = true;
                }
            });
            new_url += new_big_part.join('/');
            new_url += '/';
            if (!add_filter)
                new_url = default_link;
            if (orderby != 'default') {
                fields.orderby = orderby;
                new_url += 'orderby/' + orderby + '/';
            }
            $.post('/wp-admin/admin-ajax.php', fields, function (res) {
                if (res.success) {
                    page = 1;
                    $('ul.products').html($(res.data['items']).fadeIn());
                    last_page = res.data['last_page'];
                    load_more_button.attr('href', new_url + 'page/' + (page + 1) + '/').show();
                    if (page == last_page) {
                        load_more_button.hide();
                    }
                }
                else {
                    $('ul.products').html('<h3>No products were found matching your selection.</h3>');
                    load_more_button.hide();
                }
                $('#filter-search').html(res.data['filter']);
                can_load = true;
            }).fail(function (xhr, textStatus, e) {
                console.log(xhr.responseText);
                can_load = true;
            });
            window.history.pushState(null, null, new_url);
        }

    });
})(jQuery);