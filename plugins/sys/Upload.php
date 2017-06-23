<?php 
/**
 * @Cscms 4.x open source management system
 * @copyright 2008-2015 chshcms.com. All rights reserved.
 * @Author:Cheng Jie
 * @Dtime:2014-09-20
 */
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Upload extends Cscms_Controller {

	function __construct(){
	    parent::__construct();
	    $this->load->model('Csuser');
		$this->load->helper('string');
	}

    //上传附件
	public function index(){
        if(!$this->Csuser->User_Login(1)){
             exit('No Login');
		}
		//检测会员组上传附件权限
		$zuid=getzd('user','zid',$_SESSION['cscms__id']);
		$rowu=$this->Csdb->get_row('userzu','fid',$zuid);
		if($rowu->fid==0){
             exit(L('up_01'));
		}
	    $nums=intval($this->input->get('nums')); //支持数量
	    $types=$this->input->get('type',true);  //支持格式
        $data['tsid']=$this->input->get('tsid',true); //返回提示ID
        $data['sid']=intval($this->input->get('sid')); //返回输入框方法，0替换、1换行增加
        $data['dir']=$this->input->get('dir',true);   //上传目录
        $data['fid']=$this->input->get('fid',true);   //返回ID，一个页面多个返回可以用到
        $data['upsave']=site_url('upload/up_save');
        $data['size'] = UP_Size;
		$data['types'] = (empty($types))?"*":$types;
        $data['nums']=($nums==0)?1:$nums;
		if($data['fid']=='undefined') $data['fid']='';
		if($data['tsid']=='undefined') $data['tsid']='';
		if($data['types']=='undefined') $data['types']='*';
		if($data['dir']=='undefined') $data['dir']='other';
		$str['fid']=$rowu->fid;
		$str['id']=$_SESSION['cscms__id'];
		$str['login']=$_SESSION['cscms__login'];
        $data['key'] = sys_auth(addslashes(serialize($str)),'E');
		$this->load->get_templates('common');
        $this->load->view('upload.html',$data);
	}

    //保存附件
	public function up_save(){
        $key=$this->input->post('key',true);
        if(!$this->Csuser->User_Login(1,$key)){
             exit('No Login');
		}
		//检测会员组上传附件权限
		$key   = unserialize(stripslashes(sys_auth($key,'D')));
        $fid   = isset($key['fid'])?intval($key['fid']):0;
		if($fid==0){
             exit('You do not have permission to upload attachments of group members!');
		}
        $dir=$this->input->post('dir',true);
		if(empty($dir) || !preg_match('/^[0-9a-zA-Z\_]*$/', $dir)) {  
             $dir='other';
		}
		//上传目录
		if(UP_Mode==1 && UP_Pan!=''){
		    $path = UP_Pan.'/attachment/'.$dir.'/'.date('Ym').'/'.date('d').'/';
			$path = str_replace("//","/",$path);
		}else{
		    $path = FCPATH.'attachment/'.$dir.'/'.date('Ym').'/'.date('d').'/';
		}
		if (!is_dir($path)) {
            mkdirss($path);
        }
		$tempFile = $_FILES['Filedata']['tmp_name'];
		$file_name = $_FILES['Filedata']['name'];
		$file_size = filesize($tempFile);
        $file_ext = strtolower(trim(substr(strrchr($file_name, '.'), 1)));
        $file_type = get_file_mime($tempFile);

        //判断文件MIME类型
        $mimes = get_mimes();
        if(isset($mimes[$file_ext]) && $file_type !== false && !in_array($file_type,$mimes[$file_ext],true)){
        	exit(escape(L('up_02')));
        }

        //检查扩展名
		$ext_arr = explode("|", UP_Type);
        if(!in_array($file_ext,$ext_arr,true)){
            exit(escape(L('up_02')));
		}elseif(in_array($file_ext, array('gif', 'jpg', 'jpeg', 'jpe', 'png'), TRUE) && @getimagesize($tempFile) === FALSE){
            exit(escape(L('up_03')));
		}

        //PHP上传失败
        if (!empty($_FILES['Filedata']['error'])) {
            switch($_FILES['Filedata']['error']){
	            case '1':$error = L('up_04');break;
	            case '2':$error = L('up_05');break;
	            case '3':$error = L('up_06');break;
	            case '4':$error = L('up_07');break;
	            case '6':$error = L('up_08');break;
	            case '7':$error = L('up_09');break;
	            case '8':$error = 'File upload stopped by extension。';break;
	            case '999':default:$error = L('up_10');
            }
            exit(escape($error));
        }
        //新文件名
        //$file_name=date("YmdHis") . rand(10000, 99999) . '.' . $file_ext;
		$file_name=random_string('alnum', 20). '.' . $file_ext;
		$file_path=$path.$file_name;
		if (move_uploaded_file($tempFile, $file_path) !== false) { //上传成功

                $filepath=(UP_Mode==1)?'/'.date('Ym').'/'.date('d').'/'.$file_name : '/'.date('Ymd').'/'.$file_name;

                //判断水印
                if($dir!='links' && CS_WaterMark==1){
					if($file_ext=='jpg' || $file_ext=='png' || $file_ext=='gif' || $file_ext=='bmp' || $file_ext=='jpge'){
	                     $this->load->library('watermark');
                         $this->watermark->imagewatermark($file_path);
					}
                }

				//判断上传方式
                $this->load->library('csup');
				$res=$this->csup->up($file_path,$file_name);
				if($res){
					if($dir=='music' || $dir=='video'){
						if(UP_Mode==1){
					    	$filepath='attachment/'.$dir.$filepath;
						}else{
							$filepath = annexlink($filepath);
						}
					}
				    exit('ok=cscms='.$filepath);
				}else{
					@unlink($file_path);
                    exit('no');
				}

		}else{ //上传失败
			  exit('no');
		}
	}

	//编辑器上传保存JSON返回
	public function up_save_json(){
        $this->Csuser->User_Login();
        $dir = $this->input->get('dir',true);
		if(empty($dir) || !preg_match('/^[0-9a-zA-Z\_]*$/', $dir)) {  
            $dir = 'other';
		}
		//上传目录
		if(UP_Mode==1 && UP_Pan!=''){
		    $path = UP_Pan.'/attachment/'.$dir.'/'.date('Ym').'/'.date('d').'/';
			$path = str_replace("//","/",$path);
		}else{
		    $path = FCPATH.'attachment/'.$dir.'/'.date('Ym').'/'.date('d').'/';
		}
		if (!is_dir($path)) {
            mkdirss($path);
        }
        if(!empty($_FILES['Filedata'])){
        	$_file = $_FILES['Filedata'];
        }elseif (!empty($_FILES['file'])) {
        	$_file = $_FILES['file'];
        }else{
        	$data['code'] = 1;
        	$data['msg'] = L('up_12');
        	$data['data'] = array();
        	getjson($data,1,1);
        }
		$tempFile = $_file['tmp_name'];
		$file_name = $_file['name'];
		$file_size = filesize($tempFile);
        $file_ext = strtolower(trim(substr(strrchr($file_name, '.'), 1)));
        $file_type = get_file_mime($tempFile);

        //判断文件MIME类型
        $mimes = get_mimes();
        if(isset($mimes[$file_ext]) && $file_type !== false && !in_array($file_type,$mimes[$file_ext],true)){
        	$data['code'] = 1;
        	$data['msg'] = L('up_02');
        	$data['data'] = array();
        	getjson($data,1,1);
        }

        //检查扩展名
		$ext_arr = explode("|", UP_Type);
        if(!in_array($file_ext,$ext_arr,true)){
        	$data['code'] = 1;
        	$data['msg'] = L('up_02');
        	$data['data'] = array();
        	getjson($data,1,1);
		}elseif(in_array($file_ext, array('gif', 'jpg', 'jpeg', 'jpe', 'png'), TRUE) && @getimagesize($tempFile) === FALSE){
        	$data['code'] = 1;
        	$data['msg'] = L('up_02');
        	$data['data'] = array();
        	getjson($data,1,1);
		}

        //PHP上传失败
        if (!empty($_file['error'])) {
            switch($_file['error']){
	            case '1':$error = L('up_04');break;
	            case '2':$error = L('up_05');break;
	            case '3':$error = L('up_06');break;
	            case '4':$error = L('up_07');break;
	            case '6':$error = L('up_08');break;
	            case '7':$error = L('up_09');break;
	            case '8':$error = 'File upload stopped by extension。';break;
	            case '999':default:$error = L('up_10');
            }
        	$data['code'] = 1;
        	$data['msg'] = $error;
        	$data['data'] = array();
        	getjson($data,1,1);
        }
        //新文件名
        //$file_name=date("YmdHis") . rand(10000, 99999) . '.' . $file_ext;
		$file_name=random_string('alnum', 20). '.' . $file_ext;
		$file_path=$path.$file_name;
		if (move_uploaded_file($tempFile, $file_path) !== false) { //上传成功

                $filepath=(UP_Mode==1)?'/'.date('Ym').'/'.date('d').'/'.$file_name : '/'.date('Ymd').'/'.$file_name;

                //判断水印
                if($dir!='links' && CS_WaterMark==1){
					if($file_ext=='jpg' || $file_ext=='png' || $file_ext=='gif' || $file_ext=='bmp' || $file_ext=='jpeg'){
	                    $this->load->library('watermark');
                        $this->watermark->imagewatermark($file_path);
					}
                }
				//判断上传方式
                $this->load->library('csup');
				$res=$this->csup->up($file_path,$file_name);
				if($res){
					if(UP_Mode==1){
					    $filepath = is_ssl().Web_Url.Web_Path.'attachment/'.$dir.$filepath;
					}else{
					    $filepath = piclink($dir,$filepath);
					}
	            	$data['code'] = 0;
	            	$data['msg'] = L('up_13');
	            	$data['data'] = array(
	            		'src'=>$filepath
	            	);
	            	//解决跨域问题
	            	if($_SERVER['HTTP_HOST'] != 'localhost' && !ip2long($_SERVER['HTTP_HOST'])){
						echo "<script type=\"text/javascript\">
								var host = '".host_ym(1)."';
	    						document.domain = host;
							</script>
							<pre>".json_encode($data)."</pre>";
	            	}else{
	            		getjson($data,1,1);
	            	}
		            exit;
				}else{
					@unlink($file_path);
	            	$data['code'] = 1;
	            	$data['msg'] = L('up_12');
	            	$data['data'] = array();
	            	getjson($data,1,1);
				}

		}else{ //上传失败
        	$data['code'] = 1;
        	$data['msg'] = L('up_12');
        	$data['data'] = array();
        	getjson($data,1,1);
		}
	}
}