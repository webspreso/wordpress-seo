<?php
/**
 * @package Admin
 */

if ( !defined('WPSEO_VERSION') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}


include_once plugin_dir_path( __FILE__ ) . '/../class-gwt-table.php';
include_once plugin_dir_path( __FILE__ ) . '/../class-gwt.php';

global $wpseo_admin_pages;

$options = get_wpseo_options();
// $wpseo_admin_pages->admin_header( 'TABLE', false, 'yoast_wpseo_rss_options', 'wpseo_rss' );

// call gwt for the data
$wpseo_gwt = new WPSEO_Gwt();

$crawl_issue = get_transient( 'gwt_crawl_issues' );
if ( empty( $crawl_issue ) ){
   $crawl_issue = $wpseo_gwt->get_crawl_issues();
   set_transient( 'gwt_crawl_issues', $crawl_issue, 10 * MINUTE_IN_SECONDS );
} 


$records = array();
foreach($crawl_issue['entry'] as $entry) {
	$record = array();

	$record['id'] = $entry['id'];
	$record['updated'] = $entry['updated'];
	$record['title'] = $entry['title'];
	
	$record['crawl_type'] = $entry['wt:crawl-type'];
	$record['issue_type'] = $entry['wt:issue-type'];
	$record['url'] = $entry['wt:url'];
	
	$record['date_detected'] = $entry['wt:date-detected'];
	$record['detail'] = $entry['wt:detail'];
	$record['linked_from'] = $entry['wt:linked-from'];

	$records[] = $record;
}

$gwt_table = new WPSEO_Gwt_Table();
// Fetch, prepare, sort, and filter our data...
$gwt_table->prepare_items($records);
    
?>
	<div class="wrap">
		<a href="http://yoast.com/">
			<div class="icon32" style="background: url('http://localhost/wordpress/wp-content/plugins/wordpress-seo-git-2.0/images/wordpress-SEO-32x32.png') no-repeat;" id="yoast-icon">
				<br>
			</div>
		</a>
		<h2 id="wpseo-title">Yoast WordPress SEO: Google Webmaster Tools Crawl Issues</h2>
		<div style="min-width:400px; padding: 0 20px 0 0;" class="postbox-container" id="wpseo_content_top">
		<div class="metabox-holder">
		<div class="meta-box-sortables">
	
		<div class="wrap">
        
        
        <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
        <form id="movies-filter" method="get">
            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
			<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
            <!-- Now we can render the completed list table -->
			<?php $gwt_table->display() ?>
        </form>
        
    </div>
<?php
// $wpseo_admin_pages->admin_footer();