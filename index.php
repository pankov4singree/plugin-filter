<?php

/*
Plugin Name: SEO filter for Woocommerce
Author: PankovRA
Version: 1.0.0
*/

Class SeoFilterForWooCommerce
{
    public $old_wp_query_vars = null;

    public $can_change = true;

    public $permalink = null;

    public $product_page_slug = null;

    public $orderby = null;

    public $order = null;

    public $current_taxonomy = null;

    public function __construct()
    {
        $this->permalink = get_option('woocommerce_permalinks');
        $this->product_page_slug = get_post(get_option('woocommerce_shop_page_id'))->post_name;
        remove_all_actions('woocommerce_before_shop_loop');
        remove_all_actions('woocommerce_after_shop_loop');

        add_action('init', array($this, 'seo_filter_woocommerce_init'), 20);
        add_action('pre_get_posts', array($this, 'seo_filter_woocommerce_pre_get_posts'), 10);
        add_action('woocommerce_before_shop_loop', array($this, 'seo_filter_woocommerce_before_shop_loop'), 999999);
        add_action('woocommerce_after_shop_loop', array($this, 'seo_filter_woocommerce_after_shop_loop'), 999999);
        add_action('wp_enqueue_scripts', array($this, 'seo_filter_woocommerce_enqueue_scripts_and_styles'), 999999);
        add_action('wp_head', array($this, 'seo_filter_woocommerce_wp_head'), 999999);
        add_action('admin_head', array($this, 'seo_filter_woocommerce_wp_head'), 999999);
        add_action('wp_ajax_do_filter_woocommerce', array($this, 'seo_filter_woocommerce_do_filter_woocommerce'));
        add_action('wp_ajax_nopriv_do_filter_woocommerce', array($this, 'seo_filter_woocommerce_do_filter_woocommerce'));
        add_action('wp_ajax_seo_filter_woocommerce_load_more', array($this, 'seo_filter_woocommerce_load_more'));
        add_action('wp_ajax_nopriv_seo_filter_woocommerce_load_more', array($this, 'seo_filter_woocommerce_load_more'));

        add_filter('sanitize_title', array($this, 'seo_filter_woocommerce_sanitize_title'), 999999);
        add_filter('query_vars', array($this, 'seo_filter_woocommerce_query_vars'), 999999);
    }

    public function seo_filter_woocommerce_init()
    {
        add_rewrite_rule((empty($this->product_page_slug) ? 'shop' : $this->product_page_slug) . '/filter/?([a-zA-Z0-9_/-]+)/orderby/?([^/]+)/page/?([0-9]{1,})/?$', 'index.php?post_type=product&filter=$matches[1]&custom_order=$matches[2]&paged=$matches[3]', 'top');
        add_rewrite_rule((empty($this->product_page_slug) ? 'shop' : $this->product_page_slug) . '/filter/?([a-zA-Z0-9_/-]+)/orderby/?([^/]+)/?$', 'index.php?post_type=product&filter=$matches[1]&custom_order=$matches[2]', 'top');
        add_rewrite_rule((empty($this->product_page_slug) ? 'shop' : $this->product_page_slug) . '/filter/?([a-zA-Z0-9_/-]+)/page/?([0-9]{1,})/?$', 'index.php?post_type=product&filter=$matches[1]&paged=$matches[2]', 'top');
        add_rewrite_rule((empty($this->product_page_slug) ? 'shop' : $this->product_page_slug) . '/filter/?([a-zA-Z0-9_/-]+)/?$', 'index.php?post_type=product&filter=$matches[1]', 'top');
        add_rewrite_rule((empty($this->product_page_slug) ? 'shop' : $this->product_page_slug) . '/orderby/?([^/]+)/page/?([0-9]{1,})/?$', 'index.php?post_type=product&custom_order=$matches[1]&paged=$matches[2]', 'top');
        add_rewrite_rule((empty($this->product_page_slug) ? 'shop' : $this->product_page_slug) . '/orderby/?([^/]+)/?$', 'index.php?post_type=product&custom_order=$matches[1]', 'top');
    }

    public function seo_filter_woocommerce_enqueue_scripts_and_styles()
    {
        wp_enqueue_script('SEO-filter-js', plugin_dir_url(__FILE__) . '/script.js', array('jquery'));
        wp_enqueue_style('SEO-filter-css', plugin_dir_url(__FILE__) . '/style.css');
    }

    public function get_filters()
    {
        global $wp_query;
        $taxonomies = get_object_taxonomies('product');
        foreach ($taxonomies as $primary_key => $taxonomy) {
            if ($taxonomy != 'product_type' and $taxonomy != 'product_visibility' and $taxonomy != 'product_shipping_class') {
                $show_count = true;
                foreach ($wp_query->query_vars['tax_query'] as $value) {
                    if (is_array($value) and $value['taxonomy'] == $taxonomy)
                        $show_count = false;
                }
                echo "<div class='filter-$taxonomy filter'><ul>";
                echo "<input type='hidden' class='name-filter' value='$taxonomy'>";
                $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
                foreach ($terms as $foreign_key => $term) {
                    $query = array_filter($wp_query->query_vars, function ($value) {
                        if ($value !== '' || $value != 0)
                            return $value;
                        else
                            return null;
                    });
                    $query['posts_per_page'] = -1;
                    $used = false;
                    foreach ($query['tax_query'] as $value) {
                        if (is_array($value) and $value['taxonomy'] == $taxonomy and in_array($term->slug, array_map('strtolower', $value['terms'])))
                            $used = true;
                    }
                    array_push($query['tax_query'], array('taxonomy' => $taxonomy, 'field' => 'id', 'terms' => array($term->term_id)));
                    $count = null;
                    if ($show_count)
                        $count = new WP_Query($query);
                    echo "<li" . ($used ? " class='active'" : '') . "><input " . ($used ? " checked " : '') . "class='$taxonomy' type='checkbox' id='$primary_key-$term->slug-radio' name='filter[$taxonomy][]' value='$term->slug'><label for='$primary_key-$term->slug-radio'>$term->name" . ($show_count ? (!$used ? "<span class='count'>$count->post_count</span>" : "") : "") . "</label></li>";
                    wp_reset_postdata();
                }
                echo '</ul></div>';
            }
        }
    }

    public function seo_filter_woocommerce_before_shop_loop()
    {
        ?>
        <div class="filters">
            <span class="filter-switch">Filter</span>
            <?php echo '<form type="post" id="filter-search" class="filter-item">';
            $this->get_filters();
            echo '</form>'; ?>
            <select name="orderby" id="orderby">
                <option <?php selected($this->orderby, '') ?> value="default">Default sorting</option>
                <option <?php selected($this->orderby . '-' . $this->order, 'title-asc') ?> value="title-asc">Sort by
                    title (A-Z)
                </option>
                <option <?php selected($this->orderby . '-' . $this->order, 'title-desc') ?> value="title-desc">Sort by
                    title (Z-A)
                </option>
                <option <?php selected($this->orderby, 'rating') ?> value="rating">Sort by average rating</option>
                <option <?php selected($this->orderby, 'date') ?> value="date">Sort by newness</option>
                <option <?php selected($this->orderby, 'price') ?> value="price">Sort by price: low to high</option>
                <option <?php selected($this->orderby . '-' . $this->order, 'price-desc') ?> value="price-desc">Sort by
                    price: high to low
                </option>
            </select>
        </div>
    <?php }

    public function seo_filter_woocommerce_after_shop_loop()
    {

        global $wp_query, $wp;
        $current_url = add_query_arg($wp->query_string);
        isset($this->old_wp_query_vars) ? $wp_query->query_vars = $this->old_wp_query_vars : null;
        $wp_query->query_vars['paged'] = ($wp_query->query_vars['paged']) ? $wp_query->query_vars['paged'] : 1;
        $query = array_filter($wp_query->query_vars, function ($value) {
            if ($value !== '' || $value != 0)
                return $value;
            else
                return null;
        }); ?>
        <script>
            var load_more = <?php echo json_encode($query); ?>, last_page = <?php echo json_encode($wp_query->max_num_pages); ?>, default_link = '<?php echo get_post_type_archive_link('product'); ?>';
        </script>
        <a href="<?php echo preg_replace('/page\/([0-9]{1,})/', 'page/' . ($wp_query->query_vars['paged'] + 1), $current_url); ?>"
           <?php if ($wp_query->max_num_pages <= 0 or $wp_query->max_num_pages == $wp_query->query_vars['paged']) echo 'style="display:none;"' ?>class="button-gray send-button"
           id="load-more">Load more</a>
    <?php }


    public function seo_filter_woocommerce_query_vars($vars)
    {
        $vars[] = 'filter';
        $vars[] = 'custom_order';
        return $vars;
    }


    public function seo_filter_woocommerce_pre_get_posts($query)
    {
        if (!is_admin()) {
            if ($this->can_change == true) {
                $query->set('post_status', 'publish');
                $this->old_wp_query_vars = $query->query_vars;
                $this->can_change = false;
            }
            if (!empty($query->query_vars['filter'])) {
                $filter = explode('/', $query->query_vars['filter']);
                unset($query->query_vars['filter']);
                unset($this->old_wp_query_vars['filter']);
                $keys = $values = array();
                foreach ($filter as $key => $var) {
                    if ($key % 2 != 1) {
                        if ($var == $this->permalink['category_base'] or $var == 'product-category')
                            $keys[] = 'product_cat';
                        elseif ($var == $this->permalink['tag_base'] or $var == 'product-tag')
                            $keys[] = 'product_tag';
                        else
                            $keys[] = $var;
                    } else
                        $values[] = explode('-', $var);
                }
                $filter = array();
                $filter['keys'] = $keys;
                $filter['values'] = $values;
                foreach ($filter['values'] as $key => $value) {
                    array_push($query->query_vars['tax_query'], array('taxonomy' => $filter['keys'][$key], 'field' => 'slug', 'terms' => $value));
                }
            }
            if (!empty($query->query_vars['custom_order'])) {
                $query->query_vars = $this->get_order_by($query->query_vars, $query->query_vars['custom_order'], true);
            }
        }
        return add_filter('seo_filter_woocommerce_pre_get_posts', $query);
    }

    public function seo_filter_woocommerce_wp_head()
    {
        global $wp_rewrite;
        $wp_rewrite->extra_permastructs['product_cat']['struct'] = ($this->product_page_slug . '/filter/' . (empty($this->permalink['category_base']) ? 'product-category' : $this->permalink['category_base']) . '/%product_cat%');
        $wp_rewrite->extra_permastructs['product_tag']['struct'] = ($this->product_page_slug . '/filter/' . (empty($this->permalink['tag_base']) ? 'product-tag' : $this->permalink['tag_base']) . '/%product_tag%');
    }

    public function seo_filter_woocommerce_sanitize_title($title)
    {
        return str_replace('-', '_', $title);
    }

    public function get_order_by($query, $start_value, $unset = false)
    {
        $orderby_value = explode('-', $start_value);
        if ($unset) {
            unset($query['custom_order']);
            unset($this->old_wp_query_vars['custom_order']);
        }
        $this->orderby = esc_attr($orderby_value[0]);
        $this->order = !empty($orderby_value[1]) ? strtolower($orderby_value[1]) : 'asc';
        switch ($this->orderby) {
            case 'date' :
                $query['orderby'] = 'date ID';
                $query['order'] = ('desc' === $this->order) ? 'DESC' : 'ASC';
                break;
            case 'price' :
                $query['orderby'] = "meta_value_num ID";
                $query['order'] = ('desc' === $this->order) ? 'DESC' : 'ASC';
                $query['meta_key'] = '_price';
                break;
            case 'rating' :
                $query['meta_key'] = '_wc_average_rating';
                $query['orderby'] = array(
                    'meta_value_num' => ('asc' === $this->order) ? 'ASC' : 'DESC',
                    'ID' => ('asc' === $this->order) ? 'DESC' : 'ASC',
                );
                break;
            case 'title' :
                $query['orderby'] = 'title';
                $query['order'] = ('desc' === $this->order) ? 'DESC' : 'ASC';
                break;
            default:
                break;
        }
        return $query;
    }

    public function seo_filter_woocommerce_do_filter_woocommerce()
    {
        parse_str($_POST['filter'], $filters);
        $args = isset($_POST['query']) ? $_POST['query'] : array();
        $args['paged'] = 1;
        foreach ($filters['filter'] as $key => $filter) {
            if (!in_array('none', $filter)) {
                array_push($args['tax_query'], array('taxonomy' => $key, 'field' => 'slug', 'terms' => $filter));
            }
        }
        $args = $this->get_order_by($args, $_POST['orderby']);
        global $wp_query;
        $wp_query = new WP_Query($args);
        ob_start();
        $this->get_filters();
        $new_filters = ob_get_clean();
        if ($wp_query->have_posts()):
            ob_start();
            while ($wp_query->have_posts()):
                $wp_query->the_post();
                wc_get_template_part('content', 'product');
            endwhile;
            $data = ob_get_clean();
            wp_send_json_success(array('items' => $data, 'last_page' => $wp_query->max_num_pages, 'filter' => $new_filters));
        else:
            wp_send_json_error(array('filter' => $new_filters));
        endif;
    }

    public function seo_filter_woocommerce_load_more()
    {
        parse_str($_POST['filter'], $filters);
        $args = isset($_POST['query']) ? $_POST['query'] : array();
        $args['paged'] = $_POST['page'] + 1;
        foreach ($filters['filter'] as $key => $filter) {
            if (!in_array('none', $filter)) {
                array_push($args['tax_query'], array('taxonomy' => $key, 'field' => 'slug', 'terms' => $filter));
            }
        }
        $args = $this->get_order_by($args, $_POST['orderby']);
        ob_start();
        $the_query = new WP_Query($args);
        if ($the_query->have_posts()):
            while ($the_query->have_posts()):
                $the_query->the_post();
                wc_get_template_part('content', 'product');
            endwhile;
        endif;
        $data = ob_get_clean();
        wp_send_json_success(array('items' => $data));
    }
}


add_action('init', function () {
    $GLOBALS['seo-filter-woocommerce'] = new SeoFilterForWooCommerce();
});