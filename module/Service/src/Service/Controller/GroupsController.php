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

class IndexController extends AbstractActionController
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
	
	public function groupspostscommentsAction(){
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			$str = $this->getRequest()->getContent();

			if ((!isset($postedValues['page'])) || (trim($postedValues['page']) == '')) {
								
			}

			if ((!isset($postedValues['count'])) || (trim($postedValues['count']) == '')) {
				
			}

			if ((!isset($postedValues['userid'])) || (trim($postedValues['userid']) == '')) {
				
			}

			$groupsList= $this->getGroupsTable()->generalGroupList($limit,$offset,$user=0);
			
			$user_feeds_list = $this->getGroupsTable()->getNewsFeeds($user_id,$type,$group_id,$activity,$limit,$offset);

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
