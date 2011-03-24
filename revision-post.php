<?php
/*
  Plugin Name: Revision Post
  Plugin URI: http://www.mobvox.com.br
  Description: Permite liberar um post para revisão pública.
  Author: Daniel Pakuschewski
  Version: 0.1
  Author URI: http://www.danielpk.com.br
 */

class Revision_Post {

	/**
	 * Hold DB Name
	 * @var int
	 */
	private $dbName = 'revision';
	
	/**
	* Hold Wpdb instance
	*/
	private $Wpdb;

	public function __construct() {
		global $wpdb;
		$this->Wpdb = $wpdb;
		$this->dbName = $wpdb->prefix . $this->dbName;
		
		if (!is_admin()) {
			add_action('init', array(&$this, 'show_revision'));
		} else {
			add_action('admin_menu', array(&$this, 'meta_box'));
			add_action('save_post', array(&$this, 'save_post'), 1, 2);
		}
	}
	
	/**
	* Add revision_link box inside Post form.
	*/
	public function meta_box() {
		add_meta_box('revisionpost', 'Revisão Pública', array(&$this, 'revision_link'), 'post', 'normal', 'high');
	}

	/**
	 * Hook Save Post
	 * @param int $postID
	 */
	public function save_post($postID, $Post) {
		if(isset($_POST['revision_status']) && $_POST['revision_status'] == true && $Post->post_type != 'revision'){
			$this->Wpdb->query("INSERT INTO {$this->dbName} SET post_id = '{$postID}', created = NOW()");
		}elseif($_POST['action'] != 'inline-save' && $_POST['revision_status'] == false){
			$this->Wpdb->query("DELETE FROM {$this->dbName} WHERE post_id = '{$postID}'");
		}
	}

	/**
	 * Box inside post form.
	 * @param array $post
	 */
	public function revision_link($post) {
		if (!in_array($post->post_status, array('publish'))) {
		?>
			<p>
				<label for="revision_status" class="selectit">
					<input type="checkbox" name="revision_status" id="revision_status" value="1" <?php if ($this->isRevision($post->ID)){ echo ' checked="checked"'; } ?> /> Habilitar Revisão Pública</label>
			</p>
		<?php
			if($this->isRevision($post->ID)){
				$url = htmlentities(add_query_arg(array('p' => $post->ID, 'preview' => 'true'), get_option('home') . '/'));
				echo "<p><a href='$url'>$url</a><br /><br />\r\n";
			}
		} else {
			echo '<p>Esse post já está publicado.</p>';
		}
	}

	public function show_revision() {
		if (!is_admin() && isset($_GET['p']) && isset($_GET['preview'])) {
			$postID = (int) $_GET['p'];
			if(!$this->isRevision($postID) && !current_user_can('edit_posts')){
				wp_die('Você não ter permissão para acessar esse post.');
			}
			add_filter('posts_results', array(&$this, 'fake_publish'));
		}
	}
	
	/**
	* Change post state to publish.
	* @param array $posts
	* @return array
	*/
	public function fake_publish($posts) {
		$posts[0]->post_status = 'publish';
		return $posts;
	}
	
	/**
	* Check if $postID is checked for revision.
	* @param int $postID
	* @return boolen
	*/
	private function isRevision($postID){
		$count = $this->Wpdb->get_var($this->Wpdb->prepare("SELECT COUNT(*) FROM {$this->dbName} WHERE post_id = {$postID}"));
		if($count){
			return true;
		}
		return false;
	}
	
	/**
	* Create table into database for hold posts to revision.
	* @return void
	*/
	public function install(){
		 if($this->Wpdb->get_var("SHOW TABLES LIKE '" . $this->dbName . "'") != $this->dbName) {
			$sql = "CREATE TABLE " . $this->dbName . " (
					  id int(11) NOT NULL AUTO_INCREMENT,
					  post_id int(11) NOT NULL,
					  created datetime NOT NULL,
					  UNIQUE KEY id (id)
					);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
	}
	
	/**
	* Drop table that hold posts to revision. 
	*/
	public function uninstall(){
		 if($this->Wpdb->get_var("SHOW TABLES LIKE '" . $this->dbName . "'") == $this->dbName) {
			$sql = "DROP TABLE " . $this->dbName . ";";
			$this->Wpdb->query($sql);
		}
	}
}

$Revision_Post = new Revision_Post();

/* Plugin Install */
register_activation_hook(__FILE__, 'install' );
function install(){
	$Revision_Post = new Revision_Post();
	$Revision_Post->install();
}
/* Plugin Uninstall */
register_deactivation_hook( __FILE__, 'uninstall' );
function uninstall(){
	$Revision_Post = new Revision_Post();
	$Revision_Post->uninstall();
}