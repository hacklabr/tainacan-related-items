<?php
/*
Plugin Name: Tainacan Related Items
Description: 
Version:     1.0.0
Author:      hacklab/
Author URI:  https://hacklab.com.br/
License:     GPL2
*/

class TainacanRelatedItems
{
    protected static $instance = null;

    public function __construct()
    {
        self::$instance = $this;

        // Actions
        // Register admin page/panels
        add_action('admin_menu', [$this, 'register_admin_page'], 100);
        add_action('admin_menu', [$this, 'register_admin_page_settings'], 100);

        // Add related itens section single
        add_action('get_footer', [$this, 'add_related_itens_above_footer'], 100);

        // Add styles to related items
        add_action('wp_enqueue_scripts', [$this, 'register_styles']);
    
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    function register_admin_page()
    {
        add_submenu_page(
            'tainacan_admin',
            __('Related Items', 'tainacan-related-items'),
            __('Related Items', 'tainacan-related-items'),
            'manage_options',
            'tainacan_related_items',
            [$this, 'settings_page']
        );
    }

    public function register_admin_page_settings()
    {
        register_setting('tainacan_related_items', 'tainacan_related_items');
    }

    public function settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_GET['settings-updated'])) {
            add_settings_error('tainacan_related_items_messages', 'tainacan_related_items_message', __('Settings Saved', 'tainacan_related_items'), 'updated');
        }

        $options = get_option('tainacan_related_items', [
            'collections' => []
        ]);

        require 'admin-page.php';
    }

    /**
     * Returns Tainacan Collections
     *
     * @return Tainacan\Entities\Collection[]
     */
    public function get_collections()
    {
        $repository = \Tainacan\Repositories\Collections::get_instance();
        return $repository->fetch([], OBJECT);
    }

    /**
     * Returns Collection Taxonomies
     *
     * @param Tainacan\Entities\Collection $collection
     * @return Tainacan\Entities\Taxonomy[]
     */
    public function get_collection_taxonomies(Tainacan\Entities\Collection $collection)
    {
        $metadata = $collection->get_metadata();

        $repository = \Tainacan\Repositories\Taxonomies::get_instance();

        $metadata = array_filter($metadata, function ($item) {
            return $item->get_metadata_type() === 'Tainacan\Metadata_Types\Taxonomy' ?
                $item : null;
        });

        $taxonomies = array_map(function ($metadatum) use ($repository) {
            $options = $metadatum->get_metadata_type_options();
            $taxonomy_id = $options['taxonomy_id'];

            return $repository->fetch($taxonomy_id);
        }, $metadata);

        return $taxonomies;
    }

    /**
     * Return the ids of the terms that are attached to the item, or -1  
     *
     * @param integer $item_id
     * @param string $taxonomy
     * @return string Comma separated ids of the terms or -1 if there are no terms
     */
    public function get_item_taxonomy_terms($item_id, $taxonomy)
    {

        $_terms = get_the_terms($item_id, $taxonomy) ?: [];

        $_terms = array_map(function ($el) {
            return $el->term_taxonomy_id;
        }, $_terms);

        $result = implode(',', $_terms);
        return $result ?: '-1';
    }

    /**
     * Return the configuration for the collection
     *
     * @param item $collection_post_type
     * @return array the configuration for the collection
     */
    public function get_config($collection_post_type)
    {
        $config = get_option('tainacan_related_items');

        return isset($config[$collection_post_type]) ? $config[$collection_post_type] : [];
    }

    /**
     * Return the rating
     *
     * @param integer $item_id
     * @param string $taxonomy
     * @param float|integer $term_weight
     * @return string
     */
    public function get_taxonomy_rating_sql($item_id, $taxonomy, $term_weight)
    {
        global $wpdb;

        $terms = $this->get_item_taxonomy_terms($item_id, $taxonomy);

        $taxonomy_sql = "
            (
                SELECT 
                    COUNT($taxonomy.term_taxonomy_id) * $term_weight 

                FROM 
                    $wpdb->term_relationships $taxonomy 
                WHERE 
                    $taxonomy.object_id = p.ID AND 
                    $taxonomy.term_taxonomy_id IN ($terms)
            )
        ";

        return $taxonomy_sql;
    }

    /**
     * Return the ratings parts of the SQL query
     *
     * @param integer $term_id
     * @return array
     */
    public function get_rating_sql($term_id)
    {
        $collection_post_type = get_post_type($term_id);

        $config = $this->get_config($collection_post_type);

        $rating_sqls = [];

        if (isset($config['weights'])) {

            foreach ($config['weights'] as $taxonomy => $weight) {
                if (!$weight) {
                    continue;
                }

                $rating_sqls[] = $this->get_taxonomy_rating_sql($term_id, $taxonomy, $weight);
            }
        }

        return $rating_sqls;
    }


    /**
     * Return the related items query params
     *
     * @param integer $term_id
     * @param integer $num
     * @return array
     */
    public function get_items_query_params($term_id = null, $num = 6)
    {
        global $wpdb;

        if (is_null($term_id)) {
            $term_id = get_the_ID();
        }

        $config = $this->get_config(get_post_type($term_id));

        $collections = $config['collections'];

        $collections_string = implode("','", $collections);

        $rating_sqls = $this->get_rating_sql($term_id);
        
        $rating_sql = implode(' + ', $rating_sqls);

        $sql = "
        SELECT
            p.ID,
            (
                $rating_sql
            ) AS rating

        FROM $wpdb->posts p
        WHERE
            p.post_type IN ('$collections_string') AND
            p.post_status = 'publish' AND
            p.ID <> $term_id
        GROUP BY p.ID
        ORDER BY rating DESC, RAND()
        LIMIT $num";

        //echo "<pre>$sql</pre>";
        $result = $wpdb->get_results($sql);

        $ids = array_map(function ($el) {
            return $el->ID;
        }, $result);

        if (!$ids) {
            $ids = [-1];
        }

        return [
            'post__in' => $ids,
            'post_type' => $collections,
            'posts_per_page' => -1,
            'orderby' => 'post__in'
        ];
    }

    /**
     * Return the related items WP_Query
     *
     * @param integer $term_id
     * @param integer $num
     * @return \WP_Query
     */
    public function get_items_query($term_id = null, $num = 6)
    {
        $params = $this->get_items_query_params($term_id, $num);
        return new \WP_Query($params);
    }

    /**
     * Return the related items
     *
     * @param integer $term_id
     * @param integer $num
     * @return \WP_Posts[]
     */
    public function get_items($term_id = null, $num = 6)
    {
        $params = $this->get_items_query_params($term_id, $num);
        return get_posts($params);
    }

    
    public function add_related_itens_above_footer() {
        $collections_post_types = \Tainacan\Repositories\Repository::get_collections_db_identifiers();
        $current_post_type = get_post_type();
			
        if (in_array($current_post_type, $collections_post_types)) {
            $items = $this->get_items(); ?>
            
            <div class="row related-items">
                <h3> Itens relacionados </h3>
                <div class="related-items--wrapper">
            
            <?php
            foreach ($items as $item) { ?>
                <div class="item">
                    <a href="<?= get_the_permalink($item->ID) ?>" class="item--link">
                        <div class="item--title">
                            <?= get_the_title($item->ID) ?>
                        </div>
                        <div class="item--image">
                            <?php if ( has_post_thumbnail($item->ID) ): ?>
                                <?= get_the_post_thumbnail($item->ID, 'tainacan-medium', array('class' => 'mr-4')); ?>
                            <?php else: ?>
                                <?php echo '<div class="mr-4"><img alt="Thumbnail placeholder" src="'.get_stylesheet_directory_uri().'/assets/images/thumbnail_placeholder.png"></div>'?>
                            <?php endif; ?>  
                        </div>
                    </a>
                </div>
                
            <?php } ?>
                </div>
            </div>

            <?php
        }
    }

    public function register_styles(){
        wp_enqueue_style( 'related_items', plugins_url( 'assets/style.css' , __FILE__ ) );
    }

}

TainacanRelatedItems::getInstance();
