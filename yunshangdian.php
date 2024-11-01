<?php
/**
 *
 *+-----------------------------------------------
 *+   新浪云商店打包插件 
 *+---------------------------------------------
 * Copyright (c) 2012 yunshangdian.com.
 * All rights reserved.
 *
 * @package     yuanshangdian
 * @author      luofei614
 * @copyright   2012 yunshangdian.com.
 * @link        http://yuanshangdian.com
 * @version     1.1
 */
/*
Plugin Name: 云商店打包
Plugin URI: http://www.yuanshangdian.com/
Description: 可以将wordpress导出为新浪云商店的应用。
Author: luofei614
Version: 1.1
Author URI: http://weibo.com/luofei614
*/

global $ysd_config;//云商店配置

$ysd_config=array(
	'must_out_put_tables'=>array($wpdb->options),//必须导出数据的表
	'must_clear_tables'=>array($wpdb->users,$wpdb->usermeta),//必须清空数据的表
	//其他表用户可以自己选择是否清空数据， 默认都是清空数据
	//不被打包的目录
	'zip_with_out_dirs'=>array(
		'uploads'=>ABSPATH.'wp-content/uploads',
		'upgrade'=>ABSPATH.'wp-content/upgrade',
		'yunshangdian'=>ABSPATH.'wp-content/plugins/yunshangdian'
	)
);
define('YSD_PATH', dirname(__FILE__)).'/';
// ==================================================================
//
// 后台菜单显示
//
// ------------------------------------------------------------------

function ysd_package_menu(){
	if(function_exists('add_management_page'))
		add_management_page('云商店打包','云商店打包','administrator','yunshangdian','ysd_display');
}

// ==================================================================
//
// 导出sql数据
// 
// $clear_tables 传递不需要导出数据的表
//
// ------------------------------------------------------------------

function out_put_sql($tables,$clear_tables){
	global $wpdb;
	$sql='';
	//1,循环tables
	foreach($tables as $table){
	//1.1统一表前缀 {prefix_}  , 在安装时，会把{prefix_} 替换为正真的表前缀
	$wp_table='{prefix_}'.substr($table, strlen($wpdb->prefix));
	//1.2,  建立create语句
	$sql  .= "-- \n-- 表的结构 `$table`\n-- \n";
	$sql.=str_replace($table, $wp_table, $wpdb->get_var("SHOW CREATE TABLE {$table}",1,0));
	$sql  .= ";\n-- ";
	//1.3  判断是否在清空表中，如果不在，导入数据
	if(in_array($table, $clear_tables)) continue;
	$sql.="\n-- 导出表中的数据 `$table`\n--\n";
	$result=$wpdb->get_results("SELECT * FROM  {$table}",ARRAY_A);
	foreach($result as $arr){
		$arr=array_map(create_function('$val', 'global $wpdb; return "\'".$wpdb->escape($val)."\'";'), $arr);
		$sql.="INSERT INTO `{$wp_table}` VALUES (".implode(',',$arr).");\n";
	}
	}
	//2 返回sql内容
	return $sql;
}


// ==================================================================
//
// 获得文件列表
//
// ------------------------------------------------------------------

function get_file_list($without_upload_dir=true,$code_dir=ABSPATH){
	global $ysd_config;
	$zip_with_out_dirs=$ysd_config['zip_with_out_dirs'];
	if(!$without_upload_dir) unset($zip_with_out_dirs['uploads']);//判断是否要包含uploads目录
	$list=glob($code_dir.'*');
	$ret=array();
	foreach($list as $file){
		if(is_dir($file)){
			//判断不进行打包的目录
			if(in_array($file, $zip_with_out_dirs)) continue;
			$ret=array_merge($ret,get_file_list($without_upload_dir,$file.'/'));
		}else{
		$ret[]=$file;
		}
	}
	return $ret;
}

// ==================================================================
//
// 初始化执行
//
// ------------------------------------------------------------------


function ysd_package_init(){
	global $ysd_tables,$ysd_config,$wpdb,$package_error;
	$ysd_tables=$wpdb->get_col('SHOW TABLES');//获取所有数据库表
	if(!empty($_POST) && $_GET['page']=='yunshangdian'){
		//ob_clean();
		ob_end_clean();
		//处理数据
		if(!class_exists('ZipArchive')) require dirname(__FILE__).'/ZipArchive.class.php';
		$zip=new ZipArchive();
		$zip_path=tempnam(null, 'ysd');
		if($zip->open($zip_path,  ZipArchive::CREATE)){
		//1建立压缩包
		//1.1获得文件列表，传递不读取的目录。
		$without_upload_dir=isset($_POST['without_upload_dir'])?true:false;
		$files=get_file_list($without_upload_dir);
		$cut_length=strlen(ABSPATH);
		foreach ($files as $file) {
			$zip->addFile($file,substr($file, $cut_length));
		}
		//2，导入mysql并放入压缩包中
		$clear_tables=array_merge($_POST['clear_tables'],$ysd_config['must_clear_tables']);
		$sql=out_put_sql($ysd_tables,$clear_tables);
		$zip->addFromString('wp-admin/install.sql', $sql);
		if(isset($_POST['with_init_data'])){
			$zip->addFromString('wp-admin/install_default_data','yes');	
		}
		$zip->addFile(YSD_PATH.'/install/schema.php','wp-admin/includes/schema.php');
		$zip->addFile(YSD_PATH.'/install/upgrade.php','wp-admin/includes/upgrade.php');
		
		$zip->addFile(YSD_PATH.'/install/sae_app_wizard.xml','sae_app_wizard.xml');
		$zip->addFile(YSD_PATH.'/install/wp-config.php','wp-config.php');
		
		
		$zip->close();
		
		//4，显示下载
 	header('Accept-Ranges: bytes');
      		 header('Content-Length: ' . filesize($zip_path));
      		 header('Content-Type: application/zip');
       		header('Content-Disposition: attachment; filename=ysd-'. time() .'.zip');
      		 header("Pragma: public");
       		header("Cache-control: max-age=180");
       		header("Expires: " . gmdate("D, d M Y H:i:s", time() + 180) . "GMT");
       		header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()) . "GMT");
      		 header('Content-Encoding: none');
       		header("Content-Transfer-Encoding: binary");
       		$handle = fopen($zip_path, 'rb'); 
 		$buffer = ''; 
 		while (!feof($handle)) {
  			 $buffer = fread($handle, 1 * (1024 * 1024)); 
  			 echo $buffer; 
   			 ob_flush(); 
   			 flush(); 
 		} 
       		@unlink($zip_path);
        		exit();
		}else{
			$package_error='压缩包创建失败';	
		}

	}
}


// ==================================================================
//
// 提交页面显示
//
// ------------------------------------------------------------------

function ysd_display(){
	//error_reporting(E_ALL);
	global $wpdb,$ysd_config,$ysd_tables,$package_error;
	?>
  <div class="wrap">
  <?php screen_icon(); //显示图标  ?>
  <h2>云商店打包</h2>
  <?php
if(!empty($package_error)):
  ?>
<div class="updated" id="message"><p><strong><?=$error?></strong></p></div>
<?php
endif;
?>
  <p>本插件可帮助设计师将设计好的wordpress模板直接打包成新浪云商店应用，实现免配置的一键安装。由于打包时消耗内存较大，调整好系统相关设置。</p>
  <form action="" method="post">
  <p>
  	不导出数据的表：
	<ul>
  	<?php 
		foreach($ysd_tables as $table):
			if(in_array($table,$ysd_config['must_out_put_tables']) or in_array($table,$ysd_config['must_clear_tables'])) continue;
  	?>
  	 <li><input type="checkbox" checked="checked" name="clear_tables[]" value="<?=$table?>" />	<?=$table?></li>
  	<?php
  		endforeach;
  	?>
	</ul>
  	<br /><br />
  	不导出附件：<input type="checkbox" checked="checked" name="without_upload_dir" value="true" />
  	初始化数据：<input type="checkbox" checked="checked" name="with_init_data" value="true" />
	<br /><br />
<input type="submit" name="submit" value="开始打包" />
  </p>

  </form>
  </div>
  <?php
}
//---------------------- end ysd_display
add_action('admin_menu','ysd_package_menu');
add_action('admin_init','ysd_package_init');
?>
