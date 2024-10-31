<?php
/*
Plugin Name: PoMo Uploader
Plugin URI: https://matically.jp/pomo-uploader/
Description: Upload PO MO files
Author: Matically
Version: 1.0
Author URI: https://matically.jp/
Text Domain: pomo-uploader
Domain Path: /languages
*/

class PoMoUploader {
    function __construct() {
        add_action('admin_menu', array($this, 'add_pages'));
    }
    function add_pages() {
        load_plugin_textdomain ( 'pomo-uploader', false, basename( rtrim(dirname(__FILE__), '/') ) . '/languages' );
        add_menu_page('PoMo Uploader','PoMo Uploader',  'manage_options', 'pomo-uploader', array($this,'pomo_uploader_option_page'), '', '75.1');
    }

    function pomo_uploader_option_page() {

        $this->handlePost();

        ?>
        <!--------------------->
        <!-- plugin contents -->
        <div class="wrap">
            <h1>PoMo Uploader</h1>
            <hr>
            <h2><?php _e('Upload a File','pomo-uploader') ?></h2>
            <h4><?php _e('You can upload .po and/or .mo files to the wp-content/languages/plugins directory','pomo-uploader') ?></h4>
            <form  method="post" enctype="multipart/form-data">
                <table>
                    <tr><td><?php _e('File to be uploaded','pomo-uploader') ?></td><td><input type='file' id='upload_pomo' name='upload_pomo'></input></td></tr>
                    <tr><td><?php _e('URL for ZIP File','pomo-uploader') ?></td><td><input style="max-width: 800px;" type='text' id='zip_url' name='zip_url'></input></td></tr>
                    <tr><td><?php _e('Overwrite if the file exists','pomo-uploader') ?></td><td><input type="checkbox" name="overwrite" value="1"></td></tr>
                </table>
                <?php submit_button(__('Upload','pomo-uploader')) ?>
            </form>
            <hr>
            <h2><?php _e('Remove Files','pomo-uploader') ?></h2>
            <h4><?php _e('You can remove the selected files from the wp-content/languages/plugins directory','pomo-uploader') ?></h4>
            <form method="post" action="" onSubmit="return window.confirm('<?php _e('Are you sure you want to remove?','pomo-uploader'); ?>')">
                <table>
                    <?php foreach($this->pomoFiles as $pomoFile): ?>
                    <tr><td><input type="checkbox" name="removefiles[]" value="<?php echo $pomoFile ?>"> <?php echo $pomoFile ?>　</td></tr>
                    <?php endforeach ?>
                </table>
                <?php submit_button(__('Remove','pomo-uploader')) ?>
            </form>
            <hr>
        </div>
        <!-- plugin contents -->
        <!--------------------->
        <?php
    }

    function showMessage($message,$error){
        if($error){
            $class = 'error';
        } else {
            $class = 'updated';
        }
        echo sprintf('<div class="%s"><p>',$class);
        echo esc_html($message);
        echo '</p></div>';
    }

    function handlePost(){
        $fileUploaded = false;
        $pluginDir = dirname(dirname(__FILE__));
        $languageDir = dirname(dirname(dirname(__FILE__))) . '/languages/plugins/';
        $overwrite = sanitize_text_field($_POST['overwrite']);
        $removeFiles = $_POST['removefiles'];
        foreach($removeFiles as $removeFile){
            $removeFilePath = $languageDir. sanitize_file_name($removeFile);
            $this->showMessage(sprintf(__('%s removed','pomo-uploader'),$removeFile),false);
            unlink($removeFilePath);
        }

        if(isset($_FILES['upload_pomo'])){
            if(is_uploaded_file($_FILES['upload_pomo']['tmp_name'])){
                $pluginDir = dirname(dirname(__FILE__));
                $tmpFilePath = $_FILES['upload_pomo']['tmp_name'];
                $fileName = sanitize_file_name($_FILES['upload_pomo']['name']);
                if(preg_match('/\.(po|mo)$/i',$fileName)){
                    $storeFilePath = $languageDir . $fileName;

                    $cantOverwrite = false;
                    if(file_exists($storeFilePath) && ($overwrite != 1)){
                        $cantOverwrite = true;
                    }

                    if(!$cantOverwrite){
                        if(move_uploaded_file($tmpFilePath,$storeFilePath)){
                            if(file_exists($storeFilePath)){
                                $pluginDir = dirname(dirname(__FILE__));
                                $fileUploaded = true;
                                $this->showMessage(sprintf(__('%s uploaded','pomo-uploader'),$fileName),false);
                            }
                        }else{
                            $this->showMessage(__('error while saving','pomo-uploader'),true);
                        }
                    } else {
                        $this->showMessage(sprintf(__('%s cannot be overwritten. If you want to overwrite, please check the check box.','pomo-uploader'),$fileName),true);
                    }
                } else {
                    $this->showMessage(sprintf(__('%s cannot be uploaded. Only files with the extension po or mo can be uploaded.','pomo-uploader'),$fileName),true);
                }
            }
        }

        if(!$fileUploaded){
            $zipUrl = esc_url_raw($_POST['zip_url']);
            if($zipUrl){
                $zipPath = '/tmp/pomouploader.zip';
                $size = $this->download($zipUrl,$zipPath);
                if($size){
                    $zip = new ZipArchive;
                    if($zip->open($zipPath) === TRUE) {
                        $fileList = array();
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = sanitize_file_name($zip->getNameIndex($i));
                            if(preg_match('/\.(po|mo)$/i',$filename)){
                                $fileList[] = $filename;
                            }
                        }
                        $zip->extractTo('/tmp');
                        $zip->close();

                        foreach($fileList as $file){
                            $from = '/tmp/' .$file;
                            $to = $languageDir . $file;

                            $cantOverwrite = false;
                            if(file_exists($to) && ($overwrite != 1)){
                                $cantOverwrite = true;
                            }
            
                            if(!$cantOverwrite){
                                if(rename($from,$to)){
                                    $this->showMessage(sprintf(__('%s uploaded','pomo-uploader'),$file),false);
                                } else {
                                    $this->showMessage(sprintf(__('%s failed to upload','pomo-uploader'),$file),true);
                                }
                            } else {
                                $this->showMessage(sprintf(__('%s cannot be overwritten. If you want to overwrite, please check the check box.','pomo-uploader'),$file),true);
                            }
                        }
                    } else {
                        $this->showMessage(__('Failed to unzip','pomo-uploader'),true);
                    }
                } else {
                    $this->showMessage(sprintf(__('Failed to download zip file from %s','pomo-uploader'),$zipUrl),true);
                }
            }
        }

        $languageFiles = scandir($languageDir);
        $this->pomoFiles = array();
        foreach($languageFiles as $languageFile){
            if(preg_match('/\.(po|mo)$/i',$languageFile)){
                $this->pomoFiles[] = $languageFile;
            }
        }
    }

    // download file
    function download($fromurl, $tofile) {
        $fp = fopen($fromurl, 'r');
        $fpw = fopen($tofile, 'w');
        $size = 0;
        
        while (!feof($fp)) {
            $buffer = fread($fp, 1024);
            if ($buffer === false) {
                $size = false;
                break;
            }
    
            $wsize = fwrite($fpw, $buffer);
            if ($wsize === false) {
                $size = false;
                break;
            }
    
            $size += $wsize;
        }
    
        fclose($fp);
        fclose($fpw);
        return $size;
    }
    
}

$poMoUploader = new PoMoUploader;
