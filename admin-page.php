<style>
	.tainacan-related-items .error {
		border-left: 5px solid red;
		margin-right: 10px;
	}
	.tainacan-related-items .warning {
		border-left: 5px solid orange;
		margin-right: 10px;
	}
	.tainacan-related-items .good {
		border-left: 5px solid green;
		margin-right: 10px;
	}
	.tainacan-related-items .impartial {
		border-left: 5px solid gray;
		margin-right: 10px;
	}

    .tainacan-related-items th, .tainacan-related-items td {
        padding: 1px;
        width: auto !important;
    }

    .tainacan-related-items .collection {
        border:1px solid #ddd;
        border-right: 0;
        border-bottom: 0;
        padding: 0.5em 1em;
    }

    .tainacan-related-items .collection .collection-options {
        padding: 0 1em;
    }

    .tainacan-related-items table {
        width: auto;
    }


    .tainacan-related-items h3 {
        padding:0;
        margin:0;
    }

    .tainacan-related-items thead td {
        padding: 1em 1px;
    }
</style>

<script>
(($) => {
    $(() => {
        function setContainerStatus(checkbox){
            let $container = $('#options-' + $(checkbox).data('collectionContainer'));
            console.log('#' + $(checkbox).data('collectionContainer'), $container)
            if($(checkbox).is(':checked')){
                $container.show();
            } else {
                $container.hide();
            }
        }
        $('.js--collection-checkbox').each(function() {
            setContainerStatus(this);
        });

        $('.js--collection-checkbox').click(function(){
            setContainerStatus(this);
        });
    });
})(jQuery);
</script>

<?php 
settings_errors('tainacan_related_items_messages'); 

$collections = $this->get_collections();
$collection_names = array_map(function ($coll) { return $coll->get_db_identifier(); }, $collections);

?>
<div class="wrap tainacan-related-items">
    <h1><?php _e('Tainacan Related Items Configuration', 'tainacan-related-items'); ?></h1>
    <form action="options.php" method="post">
        <?php settings_fields('tainacan_related_items'); ?>
        <?php 
        foreach($collections as $collection): 
            $collection_id = $collection->get_db_identifier();
            $taxonomies = $this->get_collection_taxonomies($collection);
            $enabled_collections = isset($options[$collection_id]['collections']) && is_array($options[$collection_id]['collections']) ? 
                $options[$collection_id]['collections'] : $collection_names;
            ?>
            <section class="collection">
                <h2>
                    <label>
                        <input type="checkbox" 
                                name="tainacan_related_items[<?= $collection_id ?>][uses]" 
                                class="js--collection-checkbox"
                                <?php checked('on', @$options[$collection_id]['uses']) ?>
                                data-collection-container="<?= $collection_id ?>" >

                        <?= $collection->get_name() ?>
                    </label>
                </h2>
                
                <section id="options-<?= $collection_id ?>" class="js--collection-options collection-options">
                    
                    <h4><?php _e('Select the collections from which you want to get the items', 'tainacan-related-items') ?>:</h4>
                    <p>
                        <?php 
                        
                        foreach($collections as $col):

                            ?>
                            <label>
                                <input type="checkbox" 
                                        name="tainacan_related_items[<?= $collection_id ?>][collections][]" 
                                        value="<?= $col->get_db_identifier() ?>"
                                        <?php checked(true, in_array($col->get_db_identifier(), $enabled_collections)) ?> >
                                
                                <?= $col->get_name() ?>
                            </label>
                        <?php endforeach ?>
                    </p>
                    
                    <?php if($taxonomies): ?>

                        <h4><?php _e('Taxonomies weight', 'tainacan-related-items') ?></h4>
                        <p>
                            <?php _e('Leave the field blank to do not consider the taxonomy for the relationship', 'tainacan-related-items') ?>
                        </p>

                        <table class="form-table">
                            <tbody>
                                <?php foreach($taxonomies as $taxonomy): ?>
                                <tr>
                                    <th scope="row"><?= $taxonomy->get_name() ?></th>
                                    <td>
                                        <input 
                                            type="number" 
                                            step="0.1" 
                                            name="tainacan_related_items[<?= $collection_id ?>][weights][<?= $taxonomy->get_db_identifier() ?>]"
                                            value="<?= @$options[$collection_id]['weights'][$taxonomy->get_db_identifier()] ?>"
                                        >
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php _e("This collection does not use any taxonomy", 'tainacan-related-items'); ?>
                    <?php endif; ?>
                </section>
            </section>
        <?php endforeach; ?>
        <?php submit_button('Save Settings'); ?>
    </form>
</div>