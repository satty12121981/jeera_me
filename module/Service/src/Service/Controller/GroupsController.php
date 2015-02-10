<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Service\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Zend\View\Model\ViewModel;

use Zend\View\Model\JsonModel;

use Zend\View\Renderer\PhpRenderer;

use \Exception;

use Zend\Crypt\BlockCipher;

use Zend\Crypt\Password\Bcrypt;	

use User\Auth\BcryptDbAdapter as AuthAdapter;

use Zend\Session\Container;     

use Zend\Authentication\AuthenticationService;

use Zend\Mail;



use Zend\Mime\Message as MimeMessage;

use Zend\Mime\Part as MimePart;

use Zend\Authentication\Storage\Session;

class GroupsController extends AbstractActionController
{
    public $form_error ;
	protected $userTable;
	protected $userProfileTable;
	protected $userFriendTable;
	protected $userGroupTable;
	protected $userTagTable;
	
	public function init(){
        $this->flagSuccess = "Success";
		$this->flagError = "Failure";
	}

	public function groupslistAction(){
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			$str = $this->getRequest()->getContent();
			$offset = trim($postedValues['nparam']);
			$limit = trim($postedValues['countparam']);
			$user_id = trim($postedValues['userid']);

			if ((!isset($user_id)) || (trim($user_id) == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Request Not Authorised.";
				echo json_encode($dataArr);
				exit;
			}

			if (isset($limit) && !is_numeric($limit)) {
 				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Please input a valid Count Param.";
				echo json_encode($dataArr);
				exit;		
			}

			if (isset($offset) && !is_numeric($offset)) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Please input a valid N Param.";
				echo json_encode($dataArr);
				exit;
			}
			
			$dataArr[0]['usergroups'] = $this->getGroupsTable()->generalGroupList((int) $limit,(int) $offset,$user_id);
			echo json_encode($dataArr);
			exit;
		}
    }
	
	public function groupdetailsAction(){
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			$str = $this->getRequest()->getContent();
			$offset = trim($postedValues['nparam']);
			$limit = trim($postedValues['countparam']);
			$type = trim($postedValues['type']);
			$activity = trim($postedValues['activity']);
			
			$user_id = trim($postedValues['userid']);
			$group_id = trim($postedValues['groupid']);

			if ((!isset($user_id)) || (trim($user_id) == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Request Not Authorised.";
				echo json_encode($dataArr);
				exit;
			}

			if (isset($user_id) && !is_numeric($user_id)) {
 				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Please input a valid UserId.";
				echo json_encode($dataArr);
				exit;		
			}

			if ((!isset($group_id)) || (trim($group_id) == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Request Not Authorised.";
				echo json_encode($dataArr);
				exit;
			}

			if (isset($group_id) && !is_numeric($group_id)) {
 				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Please input a valid GroupId.";
				echo json_encode($dataArr);
				exit;		
			}

			if (isset($limit) && !is_numeric($limit)) {
 				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Please input a valid Count Param.";
				echo json_encode($dataArr);
				exit;		
			}

			if (isset($offset) && !is_numeric($offset)) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Please input a valid N Param.";
				echo json_encode($dataArr);
				exit;
			}
						
			$dataArr[0]['userfeeds'] = $this->getGroupsTable()->getNewsFeeds($user_id,$type,$group_id,$activity,(int) $limit,(int) $offset);
			echo json_encode($dataArr);
			exit;
		}
    }

	public function getUserTable(){
		$sm = $this->getServiceLocator();
		return  $this->userTable = (!$this->userTable)?$sm->get('User\Model\UserTable'):$this->userTable;    
	}
	
	public function getGroupsTable(){
		$sm = $this->getServiceLocator();
		return  $this->userProfileTable = (!$this->userGroupTable)?$sm->get('Groups\Model\GroupsTable'):$this->userProfileTable;    
	}

	public function getUserGroupTable(){
		$sm = $this->getServiceLocator();
		return  $this->userGroupTable = (!$this->userGroupTable)?$sm->get('Groups\Model\UserGroupTable'):$this->userGroupTable;    
	}

	public function getRecoveremailsTable(){
		$sm = $this->getServiceLocator();
		return $this->RecoveryemailsTable =(!$this->RecoveryemailsTable)?$sm->get('User\Model\RecoveryemailsTable'):$this->RecoveryemailsTable;
	}
	
}
