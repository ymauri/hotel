<?php

class Listings
{

    public function list()
    {
?>
        <div class="wrap">
            <h2>listingss</h2>
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="<?php echo admin_url('admin.php?page=tph_listings_create'); ?>">Add New</a>
                </div>
                <br class="clear">
            </div>
            <?php
            global $wpdb;
            $table_name = $wpdb->prefix . "listings";

            $rows = $wpdb->get_results("SELECT id, number, guesty_id from $table_name");
            ?>
            <table class='wp-list-table widefat fixed striped posts'>
                <tr>
                    <th class="manage-column ss-list-width">ID</th>
                    <th class="manage-column ss-list-width">Number</th>
                    <th class="manage-column ss-list-width">Guesty Id</th>
                    <th>&nbsp;</th>
                </tr>
                <?php foreach ($rows as $row) { ?>
                    <tr>
                        <td class="manage-column ss-list-width"><?php echo $row->id; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->number; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->guesty_id; ?></td>
                        <td><a href="<?php echo admin_url('admin.php?page=tph_listings_update&id=' . $row->id); ?>">Update</a></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    <?php
    }

    public function create()
    {
        $message = '';
        $number = $_POST["number"] ?? '';
        $guesty_id = $_POST["guesty_id"] ?? '';
        //insert
        if (isset($_POST['insert'])) {
            global $wpdb;
            $table_name = $wpdb->prefix . "listings";

            $wpdb->insert(
                $table_name, //table
                array('number' => $number, 'guesty_id' => $guesty_id), //data
                array('%s', '%s') //data format			
            );
            $message .= "Listing inserted";
        }
    ?>
        <div class="wrap">
            <h2>Add New Listing</h2>
            <?php if (!empty($message)) : ?><div class="updated">
                    <p><?php echo $message; ?></p>
                </div><?php endif; ?>
            <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                <table class='wp-list-table widefat fixed'>
                    <tr>
                        <th class="ss-th-width">Number</th>
                        <td><input type="text" name="number" value="<?php echo $number; ?>" class="ss-field-width" /></td>
                    </tr>
                    <tr>
                        <th class="ss-th-width">Guesty ID</th>
                        <td><input type="text" name="guesty_id" value="<?php echo $guesty_id; ?>" class="ss-field-width" /></td>
                    </tr>
                </table>
                <input type='submit' name="insert" value='Save' class='button'>
            </form>
        </div>
    <?php
    }

    public function update()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . "listings";
        $id = $_GET["id"];
        $number = $_POST["number"] ?? "";
        $guesty_id = $_POST["guesty_id"] ?? "";
        //update
        if (isset($_POST['update'])) {
            $wpdb->update(
                $table_name, //table
                array('number' => $number, 'guesty_id' => $guesty_id), //data
                array('id' => $id), //where
                array('%s', '%s'), //data format
                array('%s') //where format
            );
        }
        //delete
        else if (!empty($_POST['delete'])) {
            $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id = %s", $id));
        } else { //selecting value to update	
            $listings = $wpdb->get_results($wpdb->prepare("SELECT id, number, guesty_id from $table_name where id=%s", $id));
            foreach ($listings as $s) {
                $number = $s->number;
                $guesty_id = $s->guesty_id;
            }
        }
    ?>
        <div class="wrap">
            <h2>Listings</h2>

            <?php if (!empty($_POST['delete'])) { ?>
                <div class="updated">
                    <p>Listing deleted</p>
                </div>
                <a href="<?php echo admin_url('admin.php?page=tph_listings_list') ?>">&laquo; Back to schools list</a>

            <?php } else if (!empty($_POST['update'])) { ?>
                <div class="updated">
                    <p>Listing updated</p>
                </div>
                <a href="<?php echo admin_url('admin.php?page=tph_listings_list') ?>">&laquo; Back to listings list</a>

            <?php } else { ?>
                <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                    <table class='wp-list-table widefat fixed'>
                        <tr>
                            <th>Number</th>
                            <td><input type="text" name="number" value="<?php echo $number; ?>" /></td>
                        </tr>
                        <tr>
                            <th>Guesty ID</th>
                            <td><input type="text" name="guesty_id" value="<?php echo $guesty_id; ?>" /></td>
                        </tr>
                    </table>
                    <input type='submit' name="update" value='Save' class='button'> &nbsp;&nbsp;
                    <input type='submit' name="delete" value='Delete' class='button' onclick="return confirm('&iquest;Est&aacute;s seguro de borrar este elemento?')">
                </form>
            <?php } ?>

        </div>
<?php
    }
}
