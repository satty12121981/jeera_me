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
	protected $groupTable;
	protected $activityTable;
	protected $discussionTable;
	protected $groupMediaTable;
	protected $likeTable;
	protected $commentTable;
	protected $activityRsvpTable;
	
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
						
			$newsfeedsList = $this->getGroupsTable()->getNewsFeeds($user_id,$type,$group_id,$activity,(int) $limit,(int) $offset);

			foreach($newsfeedsList as $list){
						switch($list['type']){
							case "New Activity":
							$activity_details = array();
							$activity = $this->getActivityTable()->getActivityForFeed($list['event_id'],$user_id);
							$SystemTypeData   = $this->getGroupsTable()->fetchSystemType("Activity");
							$like_details     = $this->getLikeTable()->fetchLikesCountByReference($SystemTypeData->system_type_id,$list['event_id'],$user_id); 
							$comment_details  = $this->getCommentTable()->fetchCommentCountByReference($SystemTypeData->system_type_id,$list['event_id'],$user_id); 
							$str_liked_users  = '';
							$arr_likedUsers = array(); 
							if(!empty($like_details)&&isset($like_details['likes_counts'])){  
								$liked_users = $this->getLikeTable()->likedUsersWithoutLoggedOneWithFriendshipStatus($SystemTypeData->system_type_id,$list['event_id'],$user_id,2,0);
								
								if($like_details['is_liked']==1){
									$arr_likedUsers[] = 'you';
								}
								if($like_details['likes_counts']>0&&!empty($liked_users)){
									foreach($liked_users as $likeuser){
										$arr_likedUsers[] = $likeuser['user_given_name'];
									}
								}
								 
							}
							$rsvp_count = $this->getActivityRsvpTable()->getCountOfAllRSVPuser($activity->group_activity_id)->rsvp_count;
							$attending_users = array();
							if($rsvp_count>0){
								$attending_users = $this->getActivityRsvpTable()->getJoinMembers($activity->group_activity_id,3,0);
								//print_r($attending_users);die();
							}
							$activity_details = array(
													"group_activity_id" => $activity->group_activity_id,
													"group_activity_title" => $activity->group_activity_title,
													"group_activity_location" => $activity->group_activity_location,
													"group_activity_location_lat" => $activity->group_activity_location_lat,
													"group_activity_location_lng" => $activity->group_activity_location_lng,
													"group_activity_content" => $activity->group_activity_content,
													"group_activity_start_timestamp" => date("M d,Y H:s a",strtotime($activity->group_activity_start_timestamp)),												 
													"user_given_name" => $list['user_given_name'],
													"group_title" =>$list['group_title'],
													"group_seo_title" =>$list['group_seo_title'],
													"group_id" =>$list['group_id'],	
													"user_id" => $list['user_id'],
													"user_profile_name" => $list['user_profile_name'],												 
													"profile_photo" => $list['profile_photo'],	
													"user_fbid" => $list['user_fbid'],													
													"like_count"	=>$like_details['likes_counts'],
													"is_liked"	=>$like_details['is_liked'],
													"comment_counts"	=>$comment_details['comment_counts'],
													"is_commented"	=>$comment_details['is_commented'],
													"liked_users"	=>$arr_likedUsers,
													"rsvp_count" =>($activity->rsvp_count)?$activity->rsvp_count:0,
													"rsvp_friend_count" =>($activity->friend_count)?$activity->friend_count:0,
													"is_going"=>$activity->is_going,
													"attending_users" =>$attending_users,
													);
							$feeds[] = array('content' => $activity_details,
											'type'=>$list['type'],
											'time'=>$this->timeAgo($list['update_time']),
							); 							
							break;
							case "New Status":
								$discussion_details = array();
								$discussion = $this->getDiscussionTable()->getDiscussionForFeed($list['event_id']);
								$SystemTypeData = $this->getGroupsTable()->fetchSystemType("Discussion");
								$like_details  = $this->getLikeTable()->fetchLikesCountByReference($SystemTypeData->system_type_id,$list['event_id'],$user_id);
								$comment_details  = $this->getCommentTable()->fetchCommentCountByReference($SystemTypeData->system_type_id,$list['event_id'],$user_id); 
								$str_liked_users = '';
								$arr_likedUsers = array();
								if(!empty($like_details)&&isset($like_details['likes_counts'])){  
									$liked_users = $this->getLikeTable()->likedUsersWithoutLoggedOneWithFriendshipStatus($SystemTypeData->system_type_id,$list['event_id'],$user_id,2,0);
									
									if($like_details['is_liked']==1){
										$arr_likedUsers[] = 'you';
									}
									if($like_details['likes_counts']>0&&!empty($liked_users)){
										foreach($liked_users as $likeuser){
											$arr_likedUsers[] = $likeuser['user_given_name'];
										}
									}
									 
								}
								$discussion_details = array(
													"group_discussion_id" => $discussion->group_discussion_id,
													"group_discussion_content" => $discussion->group_discussion_content,
													"group_title" =>$list['group_title'],
													"group_seo_title" =>$list['group_seo_title'],
													"group_id" =>$list['group_id'],												
													"user_given_name" => $list['user_given_name'],
													"user_id" => $list['user_id'],
													"user_profile_name" => $list['user_profile_name'],												 
													"profile_photo" => $list['profile_photo'],
													"user_fbid" => $list['user_fbid'],
													"like_count"	=>$like_details['likes_counts'],
													"is_liked"	=>$like_details['is_liked'],
													"liked_users"	=>$arr_likedUsers,
													"comment_counts"	=>$comment_details['comment_counts'],
													"is_commented"	=>$comment_details['is_commented'],
													);
								$feeds[] = array('content' => $discussion_details,
												'type'=>$list['type'],
												'time'=>$this->timeAgo($list['update_time']),
								); 
							break;
							case "New Media":
								$media_details = array();
								$media = $this->getGroupMediaTable()->getMediaForFeed($list['event_id']);
								$video_id  = '';
								if($media->media_type == 'video')
								$video_id  = $this->get_youtube_id_from_url($media->media_content);
								$SystemTypeData = $this->getGroupsTable()->fetchSystemType("Media");
								$like_details  = $this->getLikeTable()->fetchLikesCountByReference($SystemTypeData->system_type_id,$list['event_id'],$user_id);
								$comment_details  = $this->getCommentTable()->fetchCommentCountByReference($SystemTypeData->system_type_id,$list['event_id'],$user_id); 
								$str_liked_users = '';
								$arr_likedUsers = array();
								if(!empty($like_details)&&isset($like_details['likes_counts'])){  
									$liked_users = $this->getLikeTable()->likedUsersWithoutLoggedOneWithFriendshipStatus($SystemTypeData->system_type_id,$list['event_id'],$user_id,2,0);
									
									if($like_details['is_liked']==1){
										$arr_likedUsers[] = 'you';
									}
									if($like_details['likes_counts']>0&&!empty($liked_users)){
										foreach($liked_users as $likeuser){
											$arr_likedUsers[] = $likeuser['user_given_name'];
										}
									}
									 
								}
								$media_details = array(
													"group_media_id" => $media->group_media_id,
													"media_type" => $media->media_type,
													"media_content" => $media->media_content,
													"media_caption" => $media->media_caption,
													"video_id" => $video_id,
													"group_title" =>$list['group_title'],
													"group_seo_title" =>$list['group_seo_title'],	
													"group_id" =>$list['group_id'],													
													"user_given_name" => $list['user_given_name'],
													"user_id" => $list['user_id'],
													"user_profile_name" => $list['user_profile_name'],												 
													"profile_photo" => $list['profile_photo'],
													"user_fbid" => $list['user_fbid'],
													"like_count"	=>$like_details['likes_counts'],
													"is_liked"	=>$like_details['is_liked'],	
													"liked_users"	=>$arr_likedUsers,	
													"comment_counts"	=>$comment_details['comment_counts'],
													"is_commented"	=>$comment_details['is_commented'],												
													);
								$feeds[] = array('content' => $media_details,
												'type'=>$list['type'],
												'time'=>$this->timeAgo($list['update_time']),
								); 
							break;
						}
					} 
			$dataArr[0]['groupposts'] = $feeds;
			echo json_encode($dataArr);
			exit;
		}
    }

    public function timeAgo($time_ago){ //echo $time_ago;die();
		$time_ago = strtotime($time_ago);
		$cur_time   = time();
		$time_elapsed   = $cur_time - $time_ago;
		$seconds    = $time_elapsed ;
		$minutes    = round($time_elapsed / 60 );
		$hours      = round($time_elapsed / 3600);
		$days       = round($time_elapsed / 86400 );
		$weeks      = round($time_elapsed / 604800);
		$months     = round($time_elapsed / 2600640 );
		$years      = round($time_elapsed / 31207680 );
		// Seconds
		if($seconds <= 60){
			return "just now";
		}
		//Minutes
		else if($minutes <=60){
			if($minutes==1){
				return "one minute ago";
			}
			else{
				return "$minutes minutes ago";
			}
		}
		//Hours
		else if($hours <=24){
			if($hours==1){
				return "an hour ago";
			}else{
				return "$hours hrs ago";
			}
		}
		//Days
		else if($days <= 7){
			if($days==1){
				return "yesterday";
			}else{
				return "$days days ago";
			}
		}
		//Weeks
		else if($weeks <= 4.3){
			if($weeks==1){
				return "a week ago";
			}else{
				return "$weeks weeks ago";
			}
		}
		//Months
		else if($months <=12){
			if($months==1){
				return "a month ago";
			}else{
				return "$months months ago";
			}
		}
		//Years
		else{
			if($years==1){
				return "one year ago";
			}else{
				return "$years years ago";
			}
		}
	}
	public function  get_youtube_id_from_url($url){
		if (stristr($url,'youtu.be/'))
			{preg_match('/(https:|http:|)(\/\/www\.|\/\/|)(.*?)\/(.{11})/i', $url, $final_ID); return $final_ID[4]; }
		else 
			{@preg_match('/(https:|http:|):(\/\/www\.|\/\/|)(.*?)\/(embed\/|watch.*?v=|)([a-z_A-Z0-9\-]{11})/i', $url, $IDD); return $IDD[5]; }
	}

	public function getUserTable(){
		$sm = $this->getServiceLocator();
		return  $this->userTable = (!$this->userTable)?$sm->get('User\Model\UserTable'):$this->userTable;    
	}
	
	public function getGroupsTable(){
		$sm = $this->getServiceLocator();
		return  $this->groupTable = (!$this->groupTable)?$sm->get('Groups\Model\GroupsTable'):$this->groupTable;    
	}

	public function getUserGroupTable(){
		$sm = $this->getServiceLocator();
		return  $this->userGroupTable = (!$this->userGroupTable)?$sm->get('Groups\Model\UserGroupTable'):$this->userGroupTable;    
	}

	public function getRecoveremailsTable(){
		$sm = $this->getServiceLocator();
		return $this->RecoveryemailsTable =(!$this->RecoveryemailsTable)?$sm->get('User\Model\RecoveryemailsTable'):$this->RecoveryemailsTable;
	}

	public function getActivityTable(){
		$sm = $this->getServiceLocator();
		return  $this->activityTable = (!$this->activityTable)?$sm->get('Activity\Model\ActivityTable'):$this->activityTable;    
    }

	public function getDiscussionTable(){
		$sm = $this->getServiceLocator();
		return  $this->discussionTable = (!$this->discussionTable)?$sm->get('Discussion\Model\DiscussionTable'):$this->discussionTable;    
    }

	public function getGroupMediaTable(){
		$sm = $this->getServiceLocator();
		return  $this->groupMediaTable = (!$this->groupMediaTable)?$sm->get('Groups\Model\GroupMediaTable'):$this->groupMediaTable;    
    }

	public function getLikeTable(){
		$sm = $this->getServiceLocator();
		return  $this->likeTable = (!$this->likeTable)?$sm->get('Like\Model\LikeTable'):$this->likeTable; 
	}

	public function getCommentTable(){
		$sm = $this->getServiceLocator();
		return  $this->commentTable = (!$this->commentTable)?$sm->get('Comment\Model\CommentTable'):$this->commentTable;   
	}

	public function getActivityRsvpTable(){
		$sm = $this->getServiceLocator();
		return  $this->activityRsvpTable = (!$this->activityRsvpTable)?$sm->get('Activity\Model\ActivityRsvpTable'):$this->activityRsvpTable;
    }
	
}
