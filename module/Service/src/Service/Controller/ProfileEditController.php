<?php
namespace Service\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\View\Renderer\PhpRenderer;
use \Exception;

class ProfileEditController extends AbstractActionController
{
 	protected $userTable;
	protected $userProfileTable;
	protected $userTagTable;
	
	public function init(){
        $this->flagSuccess = "Success";
		$this->flagError = "Failure";
	}
	public function ProfileEditAction(){
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$config = $this->getServiceLocator()->get('Config');
			$postedValues = $this->getRequest()->getPost();
			$str = $this->getRequest()->getContent();
			$offset = trim($postedValues['nparam']);
			$limit = trim($postedValues['countparam']);
			$accToken = strip_tags(trim($postedValues['accesstoken']));
			if ((!isset($accToken)) || (trim($accToken) == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Request Not Authorised.";
				echo json_encode($dataArr);
				exit;
			}
			if (isset($limit) && !is_numeric($limit)) {
 				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Please input a valid Count Field.";
				echo json_encode($dataArr);
				exit;		
			}
			if (isset($offset) && !is_numeric($offset)) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Please input a valid N Field.";
				echo json_encode($dataArr);
				exit;
			}
			$user_details = $this->getUserTable()->getUserByAccessToken($accToken);
			if (empty($user_details)){
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Invalid Access Token.";
				echo json_encode($dataArr);
				exit;
			}
			$ProfileEditList = $this->getProfileEditTable()->generalGroupList((int) $limit,(int) $offset,$user_details->user_id);
			foreach($ProfileEditList as $list){
				if (!empty($list['group_photo_photo']))
					$list['group_photo_photo'] = $config['pathInfo']['absolute_img_path'].$config['image_folders']['group'].$list['group_id'].'/medium/'.$list['group_photo_photo'];
				else
					$list['group_photo_photo'] = $config['pathInfo']['absolute_img_path'].'/images/group-img_def.jpg';
				$temp[]=$list;
			}
			$dataArr[0]['flag'] = "Success";
			$dataArr[0]['userProfileEdit']= $temp;
			echo json_encode($dataArr);
			exit;
		}else{
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request Not Authorised.";
			echo json_encode($dataArr);
			exit;
		}
    }
	public function getUserTable(){
		$sm = $this->getServiceLocator();
		return  $this->userTable = (!$this->userTable)?$sm->get('User\Model\UserTable'):$this->userTable;    
	}
	public function getUserProfileTable(){
		$sm = $this->getServiceLocator();
		return  $this->userProfileTable = (!$this->userProfileTable)?$sm->get('UserProfile\Model\UserProfileTable'):$this->groupTable;    
	}
	
}
