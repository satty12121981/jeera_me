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

use User\Model\User;

use User\Model\UserProfile;

use User\Model\UserFriend;

use Tag\Model\UserTag;

use User\Model\Recoveryemails;

use User\Form\Login;       

use User\Form\LoginFilter; 

use User\Form\ResetPassword;

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
	protected $RecoveryemailsTable;
	protected $WEB_STAMPTIME;
	
	public function init(){
        $this->flagSuccess = "Success";
		$this->flagError = "Failure";
	}
	
	public function registerAction(){
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			$str = $this->getRequest()->getContent();

			if ((!isset($postedValues['name'])) || (trim($postedValues['name']) == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Name is required.";
				echo json_encode($dataArr);
				exit;
			}

			if ((!isset($postedValues['email'])) || (trim($postedValues['email']) == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Email ID is required.";
				echo json_encode($dataArr);
				exit;
			}

			if(!filter_var(trim($postedValues['email']), FILTER_VALIDATE_EMAIL)){
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Invalid Email ID.";
				echo json_encode($dataArr);
				exit;
			}

			if ((!isset($postedValues['password'])) || (trim($postedValues['password']) == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Password is required.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($postedValues['country_id'])) || (trim($postedValues['country_id']) == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Country is required.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($postedValues['city_id'])) || (trim($postedValues['city_id']) == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "City is required.";
				echo json_encode($dataArr);
				exit;
			}
			$password = strip_tags($postedValues['password']);
			$password = trim($password);
			$email = strip_tags($postedValues['email']);
			$email = trim($email);
			$name = strip_tags($postedValues['name']);
			$name = trim($name);
			$user_details = $this->getUserTable()->getUserFromEmail(trim($email));
			if ($user_details) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Email ID already registered with us.";
				echo json_encode($dataArr);
				exit;
			}
			$bcrypt = new Bcrypt();
			$data['user_password'] = $bcrypt->create($password);
			$user_verification_key = md5('enckey'.rand().time());
			$data['user_verification_key'] = $user_verification_key;
			$data['user_profile_name'] = $this->make_url_friendly($name);
			$data['user_email'] = $email;
			$data['user_given_name'] = $name;
			$data['user_status'] = "not activated";
			$user = new User();
			$user->exchangeArray($data);
			$insertedUserId = $this->getUserTable()->saveUser($user);
			$user_id = $insertedUserId;
			$uniqueToken = $user_id."#".uniqid();
			$encodedUniqToken = base64_encode($uniqueToken);
			$data['user_accessToken'] = $encodedUniqToken;
			$user_details = $this->getUserTable()->getUserFromEmail($email);
			$this->getUserTable()->updateUser($data,$user_details->user_id);
			if($insertedUserId){
				$profile_data['user_profile_user_id'] = $insertedUserId;
				$profile_data['user_profile_country_id'] = strip_tags($postedValues['country_id']);
				$profile_data['user_profile_city_id'] = strip_tags($postedValues['city_id']);
				$profile_data['user_profile_status'] = "available";
				$userProfile = new UserProfile();
				$userProfile->exchangeArray($profile_data);
				$insertedUserProfileId = $this->getUserProfileTable()->saveUserProfileApi($userProfile);					 
				$this->sendVerificationEmail($user_verification_key,$insertedUserId,$data['user_email']);
				$dataArr = $this->getAllUserRelatedDetails($user_details->user_id,$data['user_accessToken']);
				echo json_encode($dataArr);
				exit;
			} else{
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Some error occurred. Please try again.";
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

    public function fbregisterAction(){
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			$str = $this->getRequest()->getContent();
			$user_details = array();
		
			if ( !empty($postedValues['email']) && filter_var($postedValues['email'], FILTER_VALIDATE_EMAIL)) {
				$user_details = $this->getUserTable()->getUserFromEmail(strip_tags(trim($postedValues['email'])));
			}
			else if( !empty($postedValues['fbid']) ) {
				$user_details = $this->getUserTable()->getUserByFbid(strip_tags(trim($postedValues['fbid'])));
			}

			if ($postedValues['fbid'] && $postedValues['accesstoken']){
				$fbid = trim($postedValues['fbid']);
				$accesstoken = trim($postedValues['accesstoken']);
			}

			if (!empty($fbid) && !empty($accesstoken)) {
				$bcrypt = new Bcrypt();

				$data['user_fbid'] = strip_tags($fbid);

				$uniqueToken = $postedValues['fbid']."#".uniqid();
				$encodedUniqToken = base64_encode($uniqueToken);
				$data['user_accessToken'] = $encodedUniqToken;
				
				if ($postedValues['email']) {
					$email = strip_tags($postedValues['email']);
					$email = trim($email);
					$data['user_email'] = $email;
				}
				if ($postedValues['name']) {
					$name = strip_tags($postedValues['name']);
					$name = trim($name);
					$data['user_given_name'] = $name;
				}

				$data['user_profile_name'] = $this->make_url_friendly($postedValues['name']);
				$data['user_status'] = "live";
			
				if (isset($user_details) && empty($user_details->user_id)){
					$data['user_register_type'] = "facebook";
					$user = new User();
					$user->exchangeArray($data);
					$insertedUserId = $this->getUserTable()->saveUser($user);
				} else {
					unset($data['user_fbid']);
					$data['user_register_type'] = "site";
					$this->getUserTable()->updateUser($data,$user_details->user_id);
					$dataArr[0]['flag'] = "Success";
					$dataArr[0]['message'] = "Login Successful.";
					$dataArr[0]['accesstoken'] = $encodedUniqToken;
					echo json_encode($dataArr);
					exit;
				}

				if($insertedUserId) {
					$profile_data['user_profile_user_id'] = $insertedUserId;
					$profile_data['user_profile_status'] = "available";
					$userProfile = new UserProfile();
					$userProfile->exchangeArray($profile_data);
					$insertedUserProfileId = $this->getUserProfileTable()->saveUserProfileApi($userProfile);					 
					$dataArr[0]['flag'] = "Success";
					$dataArr[0]['message'] = "Login Successful.";
					$dataArr[0]['accesstoken'] = $encodedUniqToken;
					echo json_encode($dataArr);
					exit;
				} else {
					$dataArr[0]['flag'] = "Failure";
					$dataArr[0]['message'] = "Some Error Occurred. Please Try Again.";
					echo json_encode($dataArr);
					exit;
				}
			} else {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "No Input Parameters.";
				echo json_encode($dataArr);
				exit;
			}
			
		} else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request Not Authorised.";
			echo json_encode($dataArr);
			exit;
		}
    }
		
	public function loginAction(){ 
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			$password = strip_tags($postedValues['password']);
			$password = trim($password);
			$email = strip_tags($postedValues['email']);
			$email = trim($email);

			if ((!isset($email)) || ($email == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Email ID is required.";
				echo json_encode($dataArr);
				exit;
			}
			if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Invalid Email ID.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($password)) || ($password == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Password is required.";
				echo json_encode($dataArr);
				exit;
			}

			$dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
	
			$authAdapter = new AuthAdapter($dbAdapter);
	
			$authAdapter
				->setTableName('y2m_user')
				->setIdentityColumn('user_email')
				->setCredentialColumn('user_password');					
	
			$authAdapter
				->setIdentity(addslashes($email))
				->setCredential($password);
	
			$result = $authAdapter->authenticate();

			if (!$result->isValid()) {
				$user_details = $this->getUserTable()->getUserFromEmail($email);
				if (empty($user_details)) {
					$dataArr[0]['flag'] = "Failure";
					$dataArr[0]['message'] = "Email ID does not exists.";
					echo json_encode($dataArr);
					exit;
				} else {
					$dataArr[0]['flag'] = "Failure";
					$dataArr[0]['message'] = "Email ID or Password is incorrect.";
					echo json_encode($dataArr);
					exit;
				}
			} else {
				$user_details = $this->getUserTable()->getUserFromEmail($email);
				$set_secretcode = $this->updateAccessToken($email,$user_details->user_id);
				$dataArr = $this->getAllUserRelatedDetails($user_details->user_id,$set_secretcode);
				echo json_encode($dataArr);
				exit;
			}
		} else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request Not Authorised.";
			echo json_encode($dataArr);
			exit;
		}
	}

	public function updateAccessToken($email,$user_id){
		$set_secretcode = $this->rec_create_secretcode($email);
		$data['user_temp_accessToken'] = $set_secretcode;
		$this->getUserTable()->updateUser($data,$user_id);
		$set_timestamp = $this->rec_create_timestamp();
		$data_array = compact('set_timestamp');
		$this->getUserTable()->updateUser($data_array,$user_id);
		return $set_secretcode;
	}
		
	public function loginaccessAction(){
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			if ((!isset($postedValues['email'])) || ($postedValues['email'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "User Name is required.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($postedValues['password'])) || ($postedValues['password'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Password is required.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($postedValues['accesstoken'])) || ($postedValues['accesstoken'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Access Token is required.";
				echo json_encode($dataArr);
				exit;
			}
			$user_details = $this->getUserTable()->getUserFromEmail($postedValues['email']);
			if (!$user_details) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Oops! Something went wrong.";
				echo json_encode($dataArr);
				exit;
			} else {
				
				$userId = $user_details->user_id;
				$secretcode = $postedValues['accesstoken'];
				$username = $postedValues['email'];
				$set_secretcode = $this->rec_create_secretcode($username);
				if ($secretcode != $set_secretcode) {
					$dataArr[0]['flag'] = "Failure";
					$dataArr[0]['message'] = "Your token has been expired.";
					echo json_encode($dataArr);
					exit;                 
				} else {
					$dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
					$authAdapter = new AuthAdapter($dbAdapter);
					$authAdapter
						->setTableName('y2m_user')
						->setIdentityColumn('user_email')
						->setCredentialColumn('user_password');
					$authAdapter
						->setIdentity(addslashes($postedValues['email']))
						->setCredential($postedValues['password']);
					$result = $authAdapter->authenticate();
					if ($result->isValid()) {
						$auth = new AuthenticationService();
						$storage = $auth->getStorage();
						$storage->write($authAdapter->getResultRowObject(null,'user_password'));
						$dataArr[0]['flag'] = "Success";
						$dataArr[0]['message'] = "Login Successful.";
						echo json_encode($dataArr);
						exit; 
					}
				}
			}
		} else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request Not Authorised.";
			echo json_encode($dataArr);
			exit;
		}
	}

	public function rec_create_secretcode($email){
        $user_details = $this->getUserTable()->getUserFromEmail($email);
		
        if ($user_details->set_timestamp != '') {
            $current_timestamp = $this->rec_create_timestamp(); //get current time stamp
 
            $diff = ( strtotime($current_timestamp) - strtotime($user_details->set_timestamp) ); //check time difference
            $minutes = round(((($diff % 604800) % 86400) % 3600) / 60, 2); //minute difference
			
            if ($minutes <= 2) {
                $set_timestamp = $user_details->set_timestamp;				
                $user_temp_accessToken = $user_details->user_temp_accessToken;
				return $user_temp_accessToken;
			} else {
                $set_timestamp = $current_timestamp;  //timestamp is expired
			}       
        }
        else {
            $set_timestamp = $this->rec_create_timestamp();
        }
        $set_secretcode = md5($user_details->user_id . $set_timestamp); 
        return $set_secretcode; //return secret code                           
    }
	
    public function rec_create_timestamp(){
        $currentTime = date('Y-m-d H:i');
        $currentDate = strtotime($currentTime);
        $futureDate = $currentDate + $this->WEB_STAMPTIME;
        $set_timestamp = date("Y-m-d H:i", $futureDate);
        return $set_timestamp;
    }
		
	public function logoutAction(){
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			if ((!isset($postedValues['userId'])) || ($postedValues['userId'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "User-Id is required.";
				echo json_encode($dataArr);
				exit;
			}
			$auth = new AuthenticationService();
			$auth->clearIdentity();
			unset($_SESSION);
			$dataArr[0]['flag'] = "Success";
			$dataArr[0]['message'] = "you have been logged out successfully.";
			echo json_encode($dataArr);
			exit;
		} else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request not authorised.";
			echo json_encode($dataArr);
			exit;
		}
	}
	
	public function forgotPasswordAction(){
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			if ((!isset($postedValues['email'])) || ($postedValues['email'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Email is required.";
				echo json_encode($dataArr);
				exit;
			} else {
				$user_data =  $this->getUserTable()->getUserFromEmail($postedValues['email']);
				if(empty($user_data)){
					$dataArr[0]['flag'] = "Failure";
					$dataArr[0]['message'] = "This email is not existing in this system.";
					echo json_encode($dataArr);
					exit;
				} else {
					$data['user_id'] = $user_data->user_id;
					$secret_code = time().rand();
					$data['secret_code'] = $secret_code;
					$data['user_email'] = $user_data->user_email;
					$data['status'] = 0;
					$recoveremails = new Recoveryemails();
					$recoveremails->exchangeArray($data);
					$recoveremails->exchangeArray($data);
					$this->getRecoveremailsTable()->ResetAllActiveRequests($user_data->user_id);
					$insertedRecoveryId = $this->getRecoveremailsTable()->saveRecovery($recoveremails);
					if($insertedRecoveryId){
						$this->sendPasswordResetMail($secret_code,$insertedRecoveryId,$user_data->user_email);
						$dataArr[0]['flag'] = "Success";
						$dataArr[0]['message'] = "Password reset option send to your email. Please check your email and follow the steps.";
						echo json_encode($dataArr);
						exit;
					}
				}
			}
		} else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request not authorised.";
			echo json_encode($dataArr);
			exit;
		}
	}
	
	public function sendPasswordResetMail($user_verification_key,$insertedRecoveryid,$emailId){

		$this->renderer = $this->getServiceLocator()->get('ViewRenderer');	 
		$user_recoverId = md5(md5('recoverid~'.$insertedRecoveryid));
		$body = $this->renderer->render('user/email/emailResetPassword.phtml', array('user_verification_key'=>$user_verification_key,'user_recoverId'=>$user_recoverId));
		$htmlPart = new MimePart($body);
		$htmlPart->type = "text/html";

		$textPart = new MimePart($body);
		$textPart->type = "text/plain";

		$body = new MimeMessage();
		$body->setParts(array($textPart, $htmlPart));

		$message = new Mail\Message();
		$message->setFrom('admin@jeera.com');
		$message->addTo($emailId);

		//$message->addReplyTo($reply);							 

		$message->setSender("Jeera");
		$message->setSubject("Reset password request");
		$message->setEncoding("UTF-8");
		$message->setBody($body);
		$message->getHeaders()->get('content-type')->setType('multipart/alternative');


		$transport = new Mail\Transport\Sendmail();
		$transport->send($message);

		return true;
	}
	
	public function sendVerificationEmail($user_verification_key,$insertedUserId,$emailId){
		$this->renderer = $this->getServiceLocator()->get('ViewRenderer');	 
		$user_insertedUserId = md5(md5('userId~'.$insertedUserId));
		$body = $this->renderer->render('user/email/emailVarification.phtml', array('user_verification_key'=>$user_verification_key,'user_insertedUserId'=>$user_insertedUserId));
		$htmlPart = new MimePart($body);
		$htmlPart->type = "text/html";

		$textPart = new MimePart($body);
		$textPart->type = "text/plain";

		$body = new MimeMessage();
		$body->setParts(array($textPart, $htmlPart));

		$message = new Mail\Message();
		$message->setFrom('admin@jeera.com');
		$message->addTo($emailId);

		//$message->addReplyTo($reply);							 

		$message->setSender("Jeera");
		$message->setSubject("Registration confirmation");
		$message->setEncoding("UTF-8");
		$message->setBody($body);
		$message->getHeaders()->get('content-type')->setType('multipart/alternative');

		$transport = new Mail\Transport\Sendmail();
		$transport->send($message);
		return true;
	}

	public function getAllUserRelatedDetails($user_id, $set_secretcode){
		$config = $this->getServiceLocator()->get('Config');
		$swapusertags = array();
		$profileDetails = array();
		$usertags = array();
		$groupCountDetails = array();
		$friends = array();
		$moveuserfriends = array();
		$dataArr = array();
		$user_details = array();

		$profileDetails = $this->getUserTable()->getProfileDetails($user_id);
		$usertags = $this->getUserTagTable()->getAllUserTags($user_id);

		if (isset($usertags)&& !empty($usertags)){
			foreach ($usertags as $key => $tags) {
				$swapusertags[$key]['tag_category_icon']= 'public/'.$config['image_folders']['tag_category'].$tags['tag_category_icon'];
				$swapusertags[$key]['tag_category_title']= $tags['tag_category_title'];
				$swapusertags[$key]['tag_title']= $tags['tag_title'];
				$swapusertags[$key]['category_id']= $tags['category_id'];
				$swapusertags[$key]['tag_id']= $tags['tag_id'];
			}
		}			
		$groupCountDetails = $this->getUserGroupTable()->getUserGroupCount($user_id);
		unset($profileDetails->user_register_type);
		unset($profileDetails->user_profile_city_id);
		unset($profileDetails->user_profile_country_id);
		unset($profileDetails->user_profile_profession);
		unset($profileDetails->user_profile_profession_at);
		unset($profileDetails->user_address);
		unset($groupCountDetails->user_group_user_id);

		$friends = $this->getUserFriendTable()->fetchAllUserFriend($user_id,2);

		if (isset($friends)&& !empty($friends)){
			foreach ($friends as $key => $friend){
				$friend_profile_pic = $this->getUserTable()->getUserProfilePic($friend->friend_id);
				$swapuserfriends = array(
					'friend_user_id' => $friend->friend_id,
					'friend_profile_name' => $friend->user_profile_name,
					'friend_given_name' => $friend->user_given_name,
					'friend_fbid' => $friend->user_fbid,
					);
				if (isset($friend_profile_pic) && !empty($friend_profile_pic->biopic)) 
					$swapuserfriends['friend_pictureurl'] = 'https://www.y2m.ae/development/jeera_me/public/'.$config['image_folders']['profile_path'].$friend->friend_id.'/'.$friend_profile_pic->biopic;
				else if(isset($friend->user_fbid) && !empty($friend->user_fbid))
					$swapuserfriends['friend_pictureurl'] = 'http://graph.facebook.com/'.$friend->user_fbid.'/picture?type=normal';
				else  
					$swapuserfriends['friend_pictureurl'] = 'https://www.y2m.ae/development/jeera_me/public/images/noimg.jpg';
				$moveuserfriends[] = $swapuserfriends;
			}
		}
		
		$profile_photo = '';
		if (!empty($profileDetails->profile_photo))
			$profile_photo = 'https://www.y2m.ae/development/jeera_me/public/'.$config['image_folders']['profile_path'].$user_id.'/'.$profileDetails->profile_photo;
		else if(isset($profileDetails->user_fbid) && !empty($profileDetails->user_fbid))
			$profile_photo = 'http://graph.facebook.com/'.$profileDetails->user_fbid.'/picture?type=normal';
		else
			$profile_photo = 'https://www.y2m.ae/development/jeera_me/public/images/noimg.jpg';

		$userprofileDetails[0] = array('user_id'=>$profileDetails->user_id,
									'user_given_name'=>$profileDetails->user_given_name,									 
									'user_profile_name'=>$profileDetails->user_profile_name,
									'user_email'=>$profileDetails->user_email,
									'user_status'=>$profileDetails->user_status,
									'user_fbid'=>$profileDetails->user_fbid,
									'user_profile_about_me'=>$profileDetails->user_profile_about_me,
									'user_profile_current_location'=>$profileDetails->user_profile_about_me,
									'user_profile_phone'=>$profileDetails->user_profile_phone,
									'country_title'=>$profileDetails->country_title,
									'country_code'=>$profileDetails->country_code,
									'country_id'=>$profileDetails->country_id,
									'city_name'=>$profileDetails->city_name,
									'city_id'=>$profileDetails->city_id,
									'profile_photo'=>$profile_photo,
									);
		$dataArr[0]['flag'] = "Success";
		$dataArr[0]['accesstoken'] = $set_secretcode;
		$dataArr[0]['userfriends'] = $moveuserfriends;
		$dataArr[0]['userinterests'] = $swapusertags;
		$dataArr[0]['userprofiledetails'] = $userprofileDetails;
		
		if (!empty($groupCountDetails->group_count))
			$dataArr[0]['usergroupscount'] = $groupCountDetails->group_count;
		else 
			$dataArr[0]['usergroupscount'] = 0;

		if ($profileDetails->user_status == "live") 
			$dataArr[0]['confirmedemail'] = "yes";
		else
			$dataArr[0]['confirmedemail'] = "no";

		return $dataArr;
	}
	
	public function make_url_friendly($string){
		
		$string = trim($string); 
		$string = preg_replace('/(\W\B)/', '',  $string); 
		$string = preg_replace('/[\W]+/',  '_', $string); 
		$string = str_replace('-', '_', $string);

		if( !empty($string) && !$this->checkProfileNameExist($string)){
			return $string; 
		}

		$length = 5;
		$randomString = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
		$string = strtolower($string).'_'.$randomString;

		if(!$this->checkProfileNameExist($string)){ //recheck again generated name exist
			return $string; 
		} 

		$string = strtolower($string).'_'.time(); 
		return $string;		
	}

	public function checkProfileNameExist($string){
		if($this->getUserTable()->checkProfileNameExist($string)){
			return true;
		} else {
			return false;
		}
	}

	public function checkUserActive($email){
		$user_data= $this->getUserTable()->getUserFromEmail($email);
		if($user_data->user_status =='live'){return true;}else{return false;}
	}

	public function getUserTable(){
		$sm = $this->getServiceLocator();
		return  $this->userTable = (!$this->userTable)?$sm->get('User\Model\UserTable'):$this->userTable;    
	}
	
	public function getUserProfileTable(){
		$sm = $this->getServiceLocator();
		return  $this->userProfileTable = (!$this->userProfileTable)?$sm->get('User\Model\UserProfileTable'):$this->userProfileTable;    
	}

	public function getUserFriendTable(){
		$sm = $this->getServiceLocator();
		return  $this->userFriendTable = (!$this->userFriendTable)?$sm->get('User\Model\UserFriendTable'):$this->userFriendTable;    
	}

	public function getUserGroupTable(){
		$sm = $this->getServiceLocator();
		return  $this->userGroupTable = (!$this->userGroupTable)?$sm->get('Groups\Model\UserGroupTable'):$this->userGroupTable;    
	}

	public function getUserTagTable(){
		$sm = $this->getServiceLocator();
		return  $this->userTagTable = (!$this->userTagTable)?$sm->get('Tag\Model\UserTagTable'):$this->userTagTable;    
	}

	public function getRecoveremailsTable(){
		$sm = $this->getServiceLocator();
		return $this->RecoveryemailsTable =(!$this->RecoveryemailsTable)?$sm->get('User\Model\RecoveryemailsTable'):$this->RecoveryemailsTable;
	}
	
}
