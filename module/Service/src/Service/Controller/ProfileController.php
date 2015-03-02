<?php
namespace Service\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\View\Renderer\PhpRenderer;
use \Exception;

use Tag\Model\UserTag;
use User\Model\User;

class ProfileController extends AbstractActionController
{
 	protected $userTable;
	protected $userProfileTable;
	protected $userTagTable;
	protected $tagTable;
	
	public function init(){
        $this->flagSuccess = "Success";
		$this->flagError = "Failure";
	}
	public function ListUserInterestsAction(){
		$error = '';
		$user_tags = array();
		$userIntrests = array();
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$config = $this->getServiceLocator()->get('Config');
			$dataArr = array();	
			$postedValues = $this->getRequest()->getPost();
			$accToken = strip_tags(trim($postedValues['accesstoken']));
			
			if ((!isset($accToken)) || (trim($accToken) == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Request Not Authorised.";
				echo json_encode($dataArr);
				exit;
			}
			
			$userinfo = $this->getUserTable()->getUserByAccessToken($accToken);
			if(empty($userinfo)){
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Invalid Access Token.";
				echo json_encode($dataArr);
				exit;
			}
			$user_id = $userinfo->user_id;
			$userInterests = $this->getUserTagTable()->getAllUserTags($user_id);
			if(!empty($userInterests)){
				foreach($userInterests as $tag_category_list){
					if (!empty($tag_category_list['tag_category_icon']))
					$tag_category_list['tag_category_icon'] = $config['pathInfo']['absolute_img_path'].$config['image_folders']['tag_category'].$tag_category_list['tag_category_icon'];
					else
					$tag_category_list['tag_category_icon'] = $config['pathInfo']['absolute_img_path'].'/images/category-icon.png';
					$tag_category_temp[] = $tag_category_list;
				}
				$userInterests = $tag_category_temp;
				$dataArr[0]['flag'] = "Success";
				$dataArr[0]['userinterests'] = $userInterests;
				echo json_encode($dataArr);
				exit;
			} else {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "No Tags(s) to the user.";
				echo json_encode($dataArr);
				exit;
			}	
		} else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request not authorised.";
			echo json_encode($dataArr);
			exit;
		}

	}
	
    public function EditUserInterestsAction(){
    	$error = '';
		$user_tags = array();
		$userIntrests = array();
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$config = $this->getServiceLocator()->get('Config');
			$dataArr = array();	
			$postedValues = $this->getRequest()->getPost();
			$accToken = strip_tags(trim($postedValues['accesstoken']));
			$edit_user_tags = (isset($postedValues['tags'])&&$postedValues['tags']!=null&&$postedValues['tags']!=''&&$postedValues['tags']!='undefined')?$postedValues['tags']:'';
			if ((!isset($accToken)) || (trim($accToken) == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Request Not Authorised.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($edit_user_tags))) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Please Input Tags.";
				echo json_encode($dataArr);
				exit;
			}
			$userinfo = $this->getUserTable()->getUserByAccessToken($accToken);
			if(empty($userinfo)){
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Invalid Access Token.";
				echo json_encode($dataArr);
				exit;
			}
			$user_id = $userinfo->user_id;
			$objUser = new User();
			$flag =0;
			if(!empty($edit_user_tags[0])){
				$edit_user_tags = explode(",", $edit_user_tags[0]);
				foreach($edit_user_tags as $tags_in){
					$data_usertags = array();
					$tag_history = $this->getTagTable()->getTag($tags_in);
					$tag_exist =  $this->getUserTagTable()->checkUserTag($user_id,$tags_in); 
					if(!empty($tag_history)&&$tag_history->tag_id!=''&&empty($tag_exist)){
						$data_usertags['user_tag_user_id'] = $user_id;
						$data_usertags['user_tag_tag_id'] = $tags_in;
						$data_usertags['user_tag_added_ip_address'] = $objUser->getUserIp();
						$objUsertag = new UserTag();
						$objUsertag->exchangeArray($data_usertags);
						$this->getUserTagTable()->saveUserTag($objUsertag);
						$flag=1;
					}							
				}
				if($flag){
					$userInterests = $this->getUserTagTable()->getAllUserTags($userinfo->user_id);
					if(!empty($userInterests)){
						foreach($userInterests as $tag_category_list){
							if (!empty($tag_category_list['tag_category_icon']))
							$tag_category_list['tag_category_icon'] = $config['pathInfo']['absolute_img_path'].$config['image_folders']['tag_category'].$tag_category_list['tag_category_icon'];
							else
							$tag_category_list['tag_category_icon'] = $config['pathInfo']['absolute_img_path'].'/images/category-icon.png';
							$tag_category_temp[] = $tag_category_list;
						}
						
						$userInterests = $tag_category_temp;
						$dataArr[0]['flag'] = "Success";
						$dataArr[0]['userinterests'] = $userInterests;
						echo json_encode($dataArr);
						exit;
					}
				}else{
					$dataArr[0]['flag'] = "Failure";
					$dataArr[0]['message'] = "Tag(s) already added to the user .";
					echo json_encode($dataArr);
					exit;
				}
			}				
		} else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request not authorised.";
			echo json_encode($dataArr);
			exit;
		}
    }

    public function DeleteUserInterestsAction(){
    	$error = '';
		$user_tags = array();
		$userIntrests = array();
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$config = $this->getServiceLocator()->get('Config');
			$dataArr = array();	
			$postedValues = $this->getRequest()->getPost();
			$accToken = strip_tags(trim($postedValues['accesstoken']));
			$edit_user_tags = (isset($postedValues['tags'])&&$postedValues['tags']!=null&&$postedValues['tags']!=''&&$postedValues['tags']!='undefined')?$postedValues['tags']:'';
			if ((!isset($accToken)) || (trim($accToken) == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Request Not Authorised.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($edit_user_tags))) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Please Input Tags.";
				echo json_encode($dataArr);
				exit;
			}
			$userinfo = $this->getUserTable()->getUserByAccessToken($accToken);
			if(empty($userinfo)){
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Invalid Access Token.";
				echo json_encode($dataArr);
				exit;
			}
			$user_id = $userinfo->user_id;
			$tag_category_temp = array();
			$flag =0;
			if(isset($edit_user_tags[0]) && !empty($edit_user_tags[0])){
				$edit_user_tags = explode(",", $edit_user_tags[0]);
				if ($this->getUserTagTable()->deleteAllUserTagsForRestAPI($user_id,array_filter($edit_user_tags))){
					$userInterests = $this->getUserTagTable()->getAllUserTags($userinfo->user_id);
					if(!empty($userInterests)){
						foreach($userInterests as $tag_category_list){
							if (!empty($tag_category_list['tag_category_icon']))
							$tag_category_list['tag_category_icon'] = $config['pathInfo']['absolute_img_path'].$config['image_folders']['tag_category'].$tag_category_list['tag_category_icon'];
							else
							$tag_category_list['tag_category_icon'] = $config['pathInfo']['absolute_img_path'].'/images/category-icon.png';
							$tag_category_temp[] = $tag_category_list;
						}
						
						$userInterests = $tag_category_temp;
						$dataArr[0]['flag'] = "Success";
						$dataArr[0]['userinterests'] = $userInterests;
						echo json_encode($dataArr);
						exit;
					} else {
						$dataArr[0]['flag'] = "Failure";
						$dataArr[0]['message'] = "No Tag(s) to the user.";
						echo json_encode($dataArr);
						exit;
					}	
				}else{
					$dataArr[0]['flag'] = "Failure";
					$dataArr[0]['message'] = "Tag(s) does not exists for the user .";
					echo json_encode($dataArr);
					exit;
				}
			}
				
		}else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request not authorised.";
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
	public function getTagTable(){
		$sm = $this->getServiceLocator();
		return  $this->tagTable = (!$this->tagTable)?$sm->get('Tag\Model\TagTable'):$this->tagTable;    
	}
	public function getUserTagTable(){
		$sm = $this->getServiceLocator();
		return  $this->userTagTable = (!$this->userTagTable)?$sm->get('Tag\Model\UserTagTable'):$this->userTagTable;    
	}
	
}
