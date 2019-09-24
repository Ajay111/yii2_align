<?php

namespace app\entites;

use Yii;
use yii\helpers\ArrayHelper;
use yii\db\Expression;
use app\models\form\ActionForm;
use app\models\ActionUser;
use app\models\Wbs;
use app\models\Meeting;
use app\models\Notification;
use app\models\ActionPoint;
use app\models\ActionHistory;
use app\services\NotificationService;

class ActionPointEntity {

    /**
     * @var \app\models\form\ActionForm
     */
    public $form_model_action;

    /**
     * @var \app\models\ActionPoint
     */
    public $model_action_point;

    /**
     * @var \app\models\ActionPoint
     */
    public $model_action_point_original;

    /**
     * @var \app\models\Meeting
     */
    public $model_meeting;

    /**
     * @var \app\models\Wbs
     */
    public $model_wbs;
    public $model_user_assigned_by;
    public $model_user_action_updated_by;
    public $model_user_assigned_to;
    public $model_user_meeting_user_temp;

    public function __construct($form_model_action = null, $model_action_point = null, $action_point_id = null) {
        $this->form_model_action = $form_model_action;
        //print_r($model_action_point);die;
        $this->model_action_point = new ActionPoint();

        if ($model_action_point != null) {
            $this->model_action_point = $model_action_point;
        } else if ($action_point_id != null) {
            $this->model_action_point = ActionPoint::findOne($action_point_id);
        }

        if ($model_action_point != null)
            $this->model_action_point_original = ActionPoint::findOne($model_action_point->id);
    }

    public function save(){
        $this->model_action_point = $this->form_model_action->action_model;
        $tempstatus = $this->form_model_action->status;
        if(empty($this->form_model_action->status)){
            $tempstatus = $this->model_action_point->status;
        }    
    
        $this->model_action_point->setAttributes([
            'origin_source' => $this->form_model_action->origin_source,
            'action' => $this->form_model_action->action,
            'reoccurring' => $this->form_model_action->reoccur != '' ? $this->form_model_action->reoccur : '0',
           'action_assigned_to' => $this->form_model_action->action_assigned_to != '' ? $this->form_model_action->action_assigned_by : 1,
           'action_assigned_by' => $this->form_model_action->action_assigned_to != '' ? $this->form_model_action->action_assigned_by : \Yii::$app->user->identity->id,
            'deadline' => $this->form_model_action->dbdatetime($this->form_model_action->deadline),
            'meeting_id' => $this->form_model_action->meeting_id != '' ? $this->form_model_action->meeting_id : '0',
            'wbs_id' => $this->form_model_action->wbs_id != '' ? $this->form_model_action->wbs_id : '0',
            'action_group_id' => $this->form_model_action->action_group_id != '' ? $this->form_model_action->action_group_id : '0',
            'action_user_id' => $this->form_model_action->action_user_id != '' ? $this->form_model_action->action_user_id : '0',
            'status' => $this->form_model_action->status != '' ? $this->form_model_action->status : '0',
            'reason' => $this->form_model_action->reason != '' ? $this->form_model_action->reason : '0',
            'approved_status' => $this->form_model_action->approved_status != '' ? $this->form_model_action->approved_status : '0',
            'user_id' => $this->form_model_action->user_id != '' ? $this->form_model_action->user_id : '0',
        ]);
       //print_r($this->model_action_point->user_id);die;
       
        if($this->model_action_point->validate()){    
            if($this->form_model_action->reoccur == ActionPoint::ACTION_REOCCUR_ONETIME) {
                if($this->model_action_point->save()) {
                    $this->assign_user_to_action();
                    $insert_update_id = $this->model_action_point->id;
                  	$this->genrateNotification();
                    return $insert_update_id;
                }else{
                   // echo $this->form_model_action->startdatetime();
                    print_r($this->form_model_action);
                    print_r($this->model_action_point->getErrors());
                    exit;
                }
            } else {
                // Re-Occur;
                throw new \yii\web\BadRequestHttpException("Bad Request, ActionEntity didn't not validate in reoccur"); // HTTP Code 400
            }

            return false;
        } else {
            throw new \yii\web\BadRequestHttpException("Bad Request, ActionEntity didn't not validate"); // HTTP Code 400
        }
        return false;
    }
public function assign_user_to_action(){
   // print_r($this->form_model_action->reason);die;
  // ActionHistory::updateAll(['status' => 0], "action_id =" . $this->model_action_point->id);
   ActionUser::updateAll(['status' => 0], "action_id =" . $this->model_action_point->id);
   //print_r($this->form_model_action->remark);die;
  if (!empty($this->form_model_action->muser)) {
                      foreach ($this->form_model_action->muser as $user_id):
                          $action_user_model = ActionUser::find()->where(['user_id' => $user_id, 'action_id' => $this->model_action_point->id])->one();
                        if (empty($action_user_model)) {
                          $action_user_model = new ActionUser();
                           }
                            $action_user_model->action_id = $this->model_action_point->id;
                            $action_user_model->user_id = $user_id;
                            $action_user_model->action = $this->form_model_action->action;
                            $action_user_model->action_assigned_to=$user_id;
                            $action_user_model->deadline=$this->form_model_action->dbdatetime($this->form_model_action->deadline);
                            $action_user_model->action_assigned_by=\Yii::$app->user->identity->id;
                            $action_user_model->remark=$this->form_model_action->remark != '' ? $this->form_model_action->remark : '0';
                            $action_user_model->status=1;
                            if($this->model_action_point->user_id  && $action_user_model->user_id==$this->model_action_point->user_id){
                                $action_user_model->approved_status=$this->form_model_action->approved_status != '' ? $this->form_model_action->approved_status : 0;
                                $action_user_model->reason=$this->form_model_action->reason != '' ? $this->form_model_action->reason : '0';
                            }
                            if(\Yii::$app->user->identity->id== $this->model_action_point->created_by)
                            $action_user_model->action_status=$this->form_model_action->action_status != '' ? $this->form_model_action->action_status : 0;
                            else if($action_user_model->user_id==\Yii::$app->user->identity->id){
                            $action_user_model->action_status=$this->form_model_action->action_status != '' ? $this->form_model_action->action_status : 0;
                            }
                           
                            $action_user_model->save();
                            endforeach;
                    }
                    if(!empty($this->form_model_action->remark)){
                         $actionhistory = new ActionHistory();
                            $actionhistory->action_id = $this->model_action_point->id;
                            $actionhistory->user_id = \Yii::$app->user->identity->id;
                            $actionhistory->action = $this->form_model_action->action;
                            $actionhistory->deadline=$this->form_model_action->dbdatetime($this->form_model_action->deadline);
                            $actionhistory->action_assigned_for=1;
                            $actionhistory->action_assigned_by=\Yii::$app->user->identity->id;
                            $actionhistory->action_assigned_to=\Yii::$app->user->identity->id;
                            $actionhistory->remark=$this->form_model_action->remark != '' ? $this->form_model_action->remark : '0';
                            $actionhistory->status=$this->form_model_action->status != '' ? $this->form_model_action->status : '0';
                          //  print_r($actionhistory->save());die;
                          if($actionhistory->save()){}else{}
                     }
                     else{}
                  
}
    public function getDetail(){
        $action = \app\helpers\Utility::object_to_array($this->model_action_point);
        $action['user'] = ActionUser::findAll(['action_id' => $this->model_action_point->id,'status'=>1]);  
        $action['history'] = ActionHistory::findAll(['action_id' => $this->model_action_point->id]); 
       $action['notification'] = \app\models\Notification::find()->select(['notification.id','notification.message','notification.detail_id','notification.user_id','notification.user_name','notification.mail_status','notification.status'])
               //->leftJoin('tbl_category', 'tbl_category.createdby = tbl_user.userid')
                ->rightJoin('action_user', 'action_user.user_id = notification.user_id and action_user.action_id=notification.detail_id')
               ->where(['notification.detail_id' => $this->model_action_point->id])
               ->andWhere(['notification.notification_status'=>1])
               ->andWhere(['action_user.status'=>1])
              //   ->createCommand();
       ->all();
        if ($this->model_action_point->wbs_id != 0)
            $action['wbs_detail'] = Wbs::findOne($this->model_action_point->wbs_id);
        return $action;
    }
	
	
    public function genrateNotification($type = "") {
         \app\models\Notification::updateAll(['notification_status' => 0], "detail_id =" . $this->model_action_point->id);
         $this->model_user_assigned_by = \app\models\UserModel::findOne($this->model_action_point->created_by);
         $this->model_user_action_updated_by = \app\models\UserModel::findOne($this->model_action_point->updated_by);
        
        $this->model_user_assigned_to = \app\models\UserModel::findOne($this->model_action_point->action_assigned_to);
        if (isset($this->model_action_point_original) && isset($this->model_action_point_original->id)) {
            if ($type == "reminder") {
				$status=false;
                foreach ($this->model_action_point_original->actionusers as $action_user_model) {
                    if($action_user_model->user_id==$this->model_action_point->created_by){
                    $status=true;
                        $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMINDER_DEADLINE);
                        $notfication_service->sendActionNotification($this);
                     }
                    else{
			$notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMINDER_DEADLINE);
                        $notfication_service->sendActionNotification($this);
			}
                }
                if($status==0){
                        $notfication_service = new NotificationService($this->model_action_point->created_by, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMINDER_DEADLINE);
                        $notfication_service->sendActionNotification($this);}
              } else if ($type == "deadline_missed") {
                  $status=false;
                  foreach ($this->model_action_point_original->actionusers as $action_user_model) {
                    if($action_user_model->user_id==$this->model_action_point->created_by){
                        $status=true;
                        $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMINDER_MISSED);
                        $notfication_service->sendActionNotification($this,$action_user_model->user_id);
                     }
                    else{
			$notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMINDER_MISSED);
                        $notfication_service->sendActionNotification($this,$action_user_model->user_id);
			}
                }
                if($status==0){
                        $notfication_service = new NotificationService($this->model_action_point->created_by, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMINDER_MISSED);
                        $notfication_service->sendActionNotification($this,$this->model_action_point->created_by);
                        }
               } else if ($this->model_action_point->approved_status == 1) {
                  // print_r('Approved now');die;
                  foreach ($this->model_action_point_original->actionusers as $action_user_model) {
                        if($action_user_model->user_id==$this->model_action_point->user_id){
                            $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_APPROVED_OTHER);
                            $notfication_service->sendActionNotification($this,$this->model_action_point_original->created_by);
                         }
                     }
                    $notfication_service = new NotificationService($this->model_action_point->created_by, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_APPROVED_SELF);
                    $notfication_service->sendActionNotification($this,$this->model_action_point_original->created_by);
                       
               }else { 
                   if ($this->model_action_point->status == 2) {
                         $status=false;
                     if($this->model_action_point->updated_by!=$this->model_action_point->created_by){
                              foreach ($this->model_action_point_original->actionusers as $action_user_model){
                                    if($action_user_model->user_id==\Yii::$app->user->identity->id){
                                     $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_COMPLETED_SELF);
                                     $notfication_service->sendActionNotification($this,\Yii::$app->user->identity->id);
                                       $status=true;
                                    }
                                    else{
                                         $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_COMPLETED_OTHER);
                                         $notfication_service->sendActionNotification($this,$this->model_action_point->updated_by);
                                    }
                                 }
                                  if($status==0){
                                        $notfication_service = new NotificationService($this->model_action_point->created_by, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_COMPLETED_OTHER);
                                        $notfication_service->sendActionNotification($this,\Yii::$app->user->identity->id);
                                  }
                                
                            }
                            else{
                                 $status=false;
                                    foreach ($this->model_action_point_original->actionusers as $action_user_model){
                                      if($action_user_model->user_id==\Yii::$app->user->identity->id){
                                    $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_COMPLETED_SELF);
                                    $notfication_service->sendActionNotification($this,\Yii::$app->user->identity->id);
                                    $status=true;
                                   }
                                   else{
                                        $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_COMPLETED_OTHER);
                                        $notfication_service->sendActionNotification($this,\Yii::$app->user->identity->id);
                                   }
                                }
                                if($status==0){
                                      if($this->model_action_point->updated_by==$this->model_action_point->created_by){
                                    $notfication_service = new NotificationService($this->model_action_point->created_by, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_COMPLETED_SELF);
                                    $notfication_service->sendActionNotification($this,\Yii::$app->user->identity->id);	
                                   } 
                                }
                            }
                       if ($this->model_action_point->meeting_id != "" && $this->model_action_point->meeting_id != "0") {
                      $this->model_meeting = Meeting::findOne($this->model_action_point->meeting_id);
                        foreach ($this->model_meeting->meetingusers as $meeting_user_model) {
                                $this->model_user_meeting_user_temp = \app\models\UserModel::findOne($meeting_user_model->user_id);
                                $notfication_service = new NotificationService($meeting_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_COMPLETED_TEAM);
                                $notfication_service->sendActionNotification($this);
                           }
                                $notfication_service = new NotificationService($this->model_action_point->action_assigned_by, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_COMPLETED_TEAM);
                                $notfication_service->sendActionNotification($this);
                    }
                }
                else if ($this->model_action_point->status == 0) {
                      if($this->model_action_point->updated_by!=$this->model_action_point->created_by){
                              foreach ($this->model_action_point_original->actionusers as $action_user_model){
                                    if($action_user_model->user_id==\Yii::$app->user->identity->id){
                                     $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMOVED_SELF);
                                     $notfication_service->sendActionNotification($this);	
                                    }
                                    else{
                                         $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMOVED_OTHER);
                                         $notfication_service->sendActionNotification($this);
                                    }
                                 }
                                $notfication_service = new NotificationService($this->model_action_point->created_by, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMOVED_OTHER);
                                $notfication_service->sendActionNotification($this);
                            }
                            else{
                                $status=false;
                                    foreach ($this->model_action_point_original->actionusers as $action_user_model){
                                      if($action_user_model->user_id==\Yii::$app->user->identity->id){
                                    $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMOVED_SELF);
                                    $notfication_service->sendActionNotification($this);
                                    $status=true;
                                   }
                                   else{
                                        $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMOVED_OTHER);
                                        $notfication_service->sendActionNotification($this,$this->model_action_point->updated_by);
                                   }
                                }
                                if($status==0){
                                      if($this->model_action_point->updated_by==$this->model_action_point->created_by){
                                    $notfication_service = new NotificationService($this->model_action_point->created_by, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMOVED_SELF);
                                    $notfication_service->sendActionNotification($this);	
                                   } 
                                }
                            }
                     }
                else {
                    if(!empty($this->form_model_action->remark)){
                         if($this->model_action_point->updated_by!=$this->model_action_point->created_by){
                              foreach ($this->model_action_point_original->actionusers as $action_user_model){
                                    if($action_user_model->user_id==\Yii::$app->user->identity->id){
                                     $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMARKS_UPDATE_SELF);
                                     $notfication_service->sendActionNotification($this);	
                                    }
                                    else{
                                         $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMARKS_UPDATE_OTHER);
                                         $notfication_service->sendActionNotification($this);
                                    }
                                 }
                                $notfication_service = new NotificationService($this->model_action_point->created_by, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMARKS_UPDATE_OTHER);
                                $notfication_service->sendActionNotification($this);
                            }
                            else{
                                    $status=false;
                                    foreach ($this->model_action_point_original->actionusers as $action_user_model){
                                      if($action_user_model->user_id==\Yii::$app->user->identity->id){
                                    $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMARKS_UPDATE_SELF);
                                    $notfication_service->sendActionNotification($this);
                                    $status=true;
                                   }
                                   else{
                                        $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMARKS_UPDATE_OTHER);
                                        $notfication_service->sendActionNotification($this);
                                   }
                                }
                                if($status==0){
                                      if($this->model_action_point->updated_by==$this->model_action_point->created_by){
                                    $notfication_service = new NotificationService($this->model_action_point->created_by, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_REMARKS_UPDATE_SELF);
                                    $notfication_service->sendActionNotification($this);	
                                   } 
                                }
                               
                            }
                    }
                    else{
                        // UPDATED SELF
                        if($this->model_action_point->updated_by!=$this->model_action_point->created_by){
                              foreach ($this->model_action_point_original->actionusers as $action_user_model){
                                    if($action_user_model->user_id==\Yii::$app->user->identity->id){
                                     $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_UPDATED_TEAM);
                                     $notfication_service->sendActionNotification($this);	
                                    }
                                    else{
                                         $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_UPDATED_OTHER);
                                         $notfication_service->sendActionNotification($this);
                                    }
                                 }
                                $notfication_service = new NotificationService($this->model_action_point->created_by, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_UPDATED_OTHER);
                                $notfication_service->sendActionNotification($this);
                            }
                        else{
                            $status=false;
                            foreach ($this->model_action_point_original->actionusers as $action_user_model){
                            if($action_user_model->user_id==\Yii::$app->user->identity->id){
                            $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_UPDATED_TEAM);
                            $notfication_service->sendActionNotification($this);
                            $status=true;
                           }
                           else{
                                $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_UPDATED_OTHER);
                                $notfication_service->sendActionNotification($this);
                           }
                        }
                        if($status==0){
                         if($this->model_action_point->updated_by==$this->model_action_point->created_by){
                            $notfication_service = new NotificationService($this->model_action_point->created_by, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_UPDATED_SELF);
                            $notfication_service->sendActionNotification($this);	
                           } 
                        }
                        }
                     
                    }
                    if ($this->model_action_point->meeting_id != "" && $this->model_action_point->meeting_id != "0") {
                        $this->model_meeting = Meeting::findOne($this->model_action_point->meeting_id);
                        foreach ($this->model_meeting->meetingusers as $meeting_user_model) {
			    if ($meeting_user_model->user_id == $this->model_action_point->action_assigned_by || $meeting_user_model->user_id == $this->model_action_point->action_assigned_to || $meeting_user_model->user_id == $this->model_action_point_original->action_assigned_to) {
                            
} else { }
                        }
                    }
                }
            }
        } else {
            // CREATED_SELF
            $status=false;
             foreach ($this->model_action_point->actionusers as $action_user_model){
                      if($action_user_model->user_id==\Yii::$app->user->identity->id){
                        $notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_CREATED_TEAM);
           		$notfication_service->sendActionNotification($this);
                        $status=TRUE;
                      }else{
                  	$notfication_service = new NotificationService($action_user_model->user_id, Notification::NOTIFICATION_TYPE_ACTION_POINTS, Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_CREATED_OTHER);
           		$notfication_service->sendActionNotification($this);
                      }
                      }
               if($status==0){
                        $notfication_service = new NotificationService($this->model_action_point->action_assigned_by, Notification::NOTIFICATION_TYPE_ACTION_POINTS,Notification::NOTIFICATION_SUB_TYPE_ACTION_POINT_CREATED_SELF);
           		$notfication_service->sendActionNotification($this);
                        }
		          
        }
    }
}

