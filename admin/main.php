<?php
//  ------------------------------------------------------------------------ //
// 本模組由 tad 製作
// 製作日期：2012-10-23
// $Id:$
// ------------------------------------------------------------------------- //

/*-----------引入檔案區--------------*/
$xoopsOption['template_main'] = "tad_discuss_adm_main.html";
include_once "header.php";
include_once "../function.php";
include_once XOOPS_ROOT_PATH."/modules/tadtools/TadUpFiles.php" ;
$TadUpFiles=new TadUpFiles("tad_discuss");

/*-----------function區--------------*/
//tad_discuss_board編輯表單
function tad_discuss_board_form($BoardID=""){
  global $xoopsDB,$xoopsUser,$xoopsModule,$xoopsTpl,$TadUpFiles;
  include_once(XOOPS_ROOT_PATH."/class/xoopsformloader.php");

  //抓取預設值
  if(!empty($BoardID)){
    $DBV=get_tad_discuss_board($BoardID);
  }else{
    $DBV=array();
  }

  //預設值設定


  //設定「BoardID」欄位預設值
  $BoardID=(!isset($DBV['BoardID']))?$BoardID:$DBV['BoardID'];

  //設定「BoardTitle」欄位預設值
  $BoardTitle=(!isset($DBV['BoardTitle']))?null:$DBV['BoardTitle'];

  //設定「BoardDesc」欄位預設值
  $BoardDesc=(!isset($DBV['BoardDesc']))?"":$DBV['BoardDesc'];

  //設定「BoardManager」欄位預設值
  $BoardManager=(!isset($DBV['BoardManager']))?$xoopsUser->uid():$DBV['BoardManager'];

  //設定「BoardEnable」欄位預設值
  $BoardEnable=(!isset($DBV['BoardEnable']))?"1":$DBV['BoardEnable'];

  $op=(empty($BoardID))?"insert_tad_discuss_board":"update_tad_discuss_board";
  //$op="replace_tad_discuss_board";

  $BoardManagerArr=explode(",",$BoardManager);

  $member_handler = xoops_gethandler('member');
  $usercount = $member_handler->getUserCount(new Criteria('level', 0, '>'));

  if ($usercount < 1000) {

    $select = new XoopsFormSelect('', 'BoardManager',$BoardManagerArr, 5, true);
    $member_handler = xoops_gethandler('member');
    $criteria = new CriteriaCompo();
    $criteria->setSort('uname');
    $criteria->setOrder('ASC');
    $criteria->setLimit(1000);
    $criteria->setStart(1);

    $select->addOptionArray($member_handler->getUserList($criteria));
    $user_menu=$select->render();
  }else{
    $user_menu="<textarea name='BoardManager' style='width:100%;'>$BoardManager</textarea>
    <div>user uid, ex:\"1,27,103\"</div>";
  }

  //取得本模組編號
  $module_id = $xoopsModule->getVar('mid');
  $moduleperm_handler =& xoops_gethandler('groupperm');
  $read_group=$moduleperm_handler->getGroupIds("forum_read", $BoardID, $module_id);
  $post_group=$moduleperm_handler->getGroupIds("forum_post", $BoardID, $module_id);

  if(empty($read_group))$read_group=array(1,2,3);
  if(empty($post_group))$post_group=array(1,2);

  //可見群組
  $SelectGroup_name = new XoopsFormSelectGroup("", "forum_read", true,$read_group, 6, true);
  $enable_read_group = $SelectGroup_name->render();

  //可上傳群組
  $SelectGroup_name = new XoopsFormSelectGroup("", "forum_post", true,$post_group, 6, true);
  $enable_post_group = $SelectGroup_name->render();



  if(!file_exists(TADTOOLS_PATH."/formValidator.php")){
    redirect_header("index.php",3, _MA_NEED_TADTOOLS);
  }
  include_once TADTOOLS_PATH."/formValidator.php";
  $formValidator= new formValidator("#myForm",true);
  $formValidator_code=$formValidator->render();

  $TadUpFiles->set_col("BoardID",$BoardID); //若 $show_list_del_file ==true 時一定要有
  $upform=$TadUpFiles->upform(false,"upfile",1,true,"gif|jpg|png|GIF|JPG|PNG");


  $xoopsTpl->assign('formValidator_code',$formValidator_code);
  $xoopsTpl->assign('BoardID',$BoardID);
  $xoopsTpl->assign('BoardTitle',$BoardTitle);
  $xoopsTpl->assign('BoardDesc',$BoardDesc);
  $xoopsTpl->assign('enable_read_group',$enable_read_group);
  $xoopsTpl->assign('enable_post_group',$enable_post_group);
  $xoopsTpl->assign('upform',$upform);
  $xoopsTpl->assign('user_menu',$user_menu);
  $xoopsTpl->assign('BoardEnable1',chk($BoardEnable,'1'));
  $xoopsTpl->assign('BoardEnable0',chk($BoardEnable,'0'));
  $xoopsTpl->assign('next_op',$op);

  $xoopsTpl->assign('op','tad_discuss_board_form');
}





//更新tad_discuss_board某一筆資料
function update_tad_discuss_board($BoardID=""){
  global $xoopsDB,$xoopsUser,$TadUpFiles;


  $myts = MyTextSanitizer::getInstance();
  $_POST['BoardDesc']=$myts->addSlashes($_POST['BoardDesc']);
  $_POST['BoardTitle']=$myts->addSlashes($_POST['BoardTitle']);

  $BoardManager=is_array($_POST['BoardManager'])?implode(',',$_POST['BoardManager']):$_POST['BoardManager'];

  $sql = "update `".$xoopsDB->prefix("tad_discuss_board")."` set
   `ofBoardID` = '{$_POST['ofBoardID']}' ,
   `BoardTitle` = '{$_POST['BoardTitle']}' ,
   `BoardDesc` = '{$_POST['BoardDesc']}' ,
   `BoardManager` = '{$BoardManager}' ,
   `BoardEnable` = '{$_POST['BoardEnable']}'
  where `BoardID` = '$BoardID'";
  $xoopsDB->queryF($sql) or redirect_header($_SERVER['PHP_SELF'],3, mysql_error());

  //寫入權限
  saveItem_Permissions($_POST['forum_read'], $BoardID, 'forum_read');
  saveItem_Permissions($_POST['forum_post'], $BoardID, 'forum_post');

  $TadUpFiles->set_col("BoardID" , $BoardID);
  $TadUpFiles->upload_file("upfile",1024,120,NULL,"",true);
  return $BoardID;
}

//列出所有tad_discuss_board資料
function list_tad_discuss_board($show_function=1){
  global $xoopsDB , $xoopsModule , $isAdmin ,$xoopsTpl,$TadUpFiles;

  $sql = "select * from `".$xoopsDB->prefix("tad_discuss_board")."` order by BoardSort";
  $result = $xoopsDB->query($sql) or redirect_header($_SERVER['PHP_SELF'],3, mysql_error());

  $function_title=($show_function)?"<th>"._TAD_FUNCTION."</th>":"";

  $all_content="";
  $i=0;
  while($all=$xoopsDB->fetchArray($result)){
    //以下會產生這些變數： $BoardID , $BoardTitle , $BoardDesc , $BoardManager , $BoardEnable
    foreach($all as $k=>$v){
      $$k=$v;
    }

    $delbtn=($BoardEnable=='1')?"<a href='{$_SERVER['PHP_SELF']}?op=changeBoardStatus&act=0&BoardID=$BoardID' class='link_button'><img src='../images/cancel.png' alt='"._TAD_UNABLE."'>"._TAD_UNABLE."</a>":"<a href=\"javascript:delete_tad_discuss_board_func($BoardID);\" class='link_button'><img src='../images/delete.png' alt='"._TAD_DEL."'>"._TAD_DEL."</a><a href='{$_SERVER['PHP_SELF']}?op=changeBoardStatus&act=1&BoardID=$BoardID' class='link_button'><img src='../images/accept.png' alt='"._TAD_ENABLE."'>"._TAD_ENABLE."</a>";

    $fun=($show_function)?"
    <td>
    $delbtn
    <a href='{$_SERVER['PHP_SELF']}?op=tad_discuss_board_form&BoardID=$BoardID' class='link_button'><img src='../images/edit.png' alt='"._TAD_EDIT."'>"._TAD_EDIT."</a>
    <div>
    <form action='main.php' method='post'>
    <select name='NewBoardID' style='width:100px;'>
    <option value=''>"._MA_TADDISCUS_MOVE."</option>
    ".get_tad_discuss_board_menu_options($BoardID)."
    </select>
    <input type='hidden' name='BoardID' value='$BoardID'>
    <input type='hidden' name='op' value='moveToBoardID'>
    <input type='submit' value='"._MA_TADDISCUS_MERGE."'>
    </form>
    </div>
    </td>":"";

    //$pic=get_pic_file('BoardID' , $BoardID , 1 , 'thumb');

    $TadUpFiles->set_col('BoardID' , $BoardID);
    $pic=$TadUpFiles->get_pic_file('thumb'); //thumb 小圖, images 大圖（default）, file 檔案


    $pic=empty($pic)?"../images/board.png":$pic;


    $BoardNum=get_board_num($BoardID);
    $BoardNum2=get_board_num($BoardID,false);

    $color=($BoardEnable=='0')?"#f5f5f5":"white";

    $BoardManagerArr=explode(",",$BoardManager);
    $manager="";
    foreach($BoardManagerArr as $uid){
      if(empty($uid))continue;
      $uid_name=XoopsUser::getUnameFromId($uid,1);
      if(empty($uid_name))$uid_name=XoopsUser::getUnameFromId($uid,0);
      $manager[]=$uid_name;
    }
    $BoardManager=implode(' , ',$manager);

    $BoardEnable=($BoardEnable==1)? _YES : _NO;

    $all_content[$i]['BoardID']=$BoardID;
    $all_content[$i]['color']=$color;
    $all_content[$i]['pic']=$pic;
    $all_content[$i]['BoardTitle']=$BoardTitle;
    $all_content[$i]['BoardDesc']=$BoardDesc;
    $all_content[$i]['BoardNum']=sprintf(_MA_TADDISCUS_BOARD_DISCUSS,number_format($BoardNum));
    $all_content[$i]['BoardNum2']=sprintf(_MA_TADDISCUS_ALL_DISCUSS,number_format($BoardNum2));
    $all_content[$i]['BoardManager']=$BoardManager;
    $all_content[$i]['BoardEnable']=$BoardEnable;
    $all_content[$i]['fun']=$fun;
    $i++;
  }


  $xoopsTpl->assign('function_title',$function_title);
  $xoopsTpl->assign('all_content',$all_content);

  $xoopsTpl->assign('jquery',get_jquery(true));
}

//取得tad_discuss_board分類選單的選項（單層選單）
function get_tad_discuss_board_menu_options($default_BoardID="0"){
  global $xoopsDB,$xoopsModule;
  $sql = "select `BoardID` , `ofBoardID` , `BoardTitle` from `".$xoopsDB->prefix("tad_discuss_board")."` order by `BoardSort`";
  $result = $xoopsDB->query($sql) or redirect_header($_SERVER['PHP_SELF'],3, mysql_error());


  $option="";
  while(list($BoardID , $ofBoardID , $BoardTitle)=$xoopsDB->fetchRow($result)){
    if($BoardID==$default_BoardID)continue;
    $option.="<option value=$BoardID>{$BoardTitle}</option>";

  }
  return $option;
}


//刪除tad_discuss_board某筆資料資料
function delete_tad_discuss_board($BoardID=""){
  global $xoopsDB , $isAdmin , $TadUpFiles;
  $sql = "select DiscussID from ".$xoopsDB->prefix("tad_discuss")." where BoardID='$BoardID' and ReDiscussID=0";
  $result = $xoopsDB->query($sql) or redirect_header($_SERVER['PHP_SELF'],3, mysql_error());

  while(list($DiscussID)=$xoopsDB->fetchRow($result)){
    delete_tad_discuss($DiscussID);
  }

  $sql = "delete from `".$xoopsDB->prefix("tad_discuss_board")."` where `BoardID` = '{$BoardID}'";
  $xoopsDB->queryF($sql) or redirect_header($_SERVER['PHP_SELF'],3, mysql_error());
  //del_files('' , "BoardID" , $BoardID);
  $TadUpFiles->set_col("BoardID" , $BoardID); //若要整個刪除
  $TadUpFiles->del_files();
}


//合併討論區
function moveToBoardID($BoardID='',$NewBoardID=''){
  global $xoopsDB,$xoopsUser , $TadUpFiles;

  if(empty($BoardID) or empty($NewBoardID))return;

  $sql = "update `".$xoopsDB->prefix("tad_discuss")."` set `BoardID` = '{$NewBoardID}' where `BoardID` = '$BoardID'";
  $xoopsDB->queryF($sql) or redirect_header($_SERVER['PHP_SELF'],3, mysql_error());

  $sql = "delete from `".$xoopsDB->prefix("tad_discuss_board")."` where `BoardID` = '$BoardID'";
  $xoopsDB->queryF($sql) or redirect_header($_SERVER['PHP_SELF'],3, mysql_error());

  $TadUpFiles->set_col("BoardID" , $BoardID); //若要整個刪除
  $TadUpFiles->del_files();
}

function changeBoardStatus($BoardID='',$act='0'){
  global $xoopsDB,$xoopsUser;

  if(empty($BoardID))return;

  $sql = "update `".$xoopsDB->prefix("tad_discuss_board")."` set `BoardEnable` = '{$act}' where `BoardID` = '$BoardID'";
  $xoopsDB->queryF($sql) or redirect_header($_SERVER['PHP_SELF'],3, mysql_error());

}
/*-----------執行動作判斷區----------*/
$op = empty($_REQUEST['op'])? "":$_REQUEST['op'];
$DiscussID=empty($_REQUEST['DiscussID'])?"":intval($_REQUEST['DiscussID']);
$BoardID=empty($_REQUEST['BoardID'])?"":intval($_REQUEST['BoardID']);
$NewBoardID=empty($_REQUEST['NewBoardID'])?"":intval($_REQUEST['NewBoardID']);
$files_sn=empty($_REQUEST['files_sn'])?"":intval($_REQUEST['files_sn']);


switch($op){
  /*---判斷動作請貼在下方---*/

  //替換資料
  case "replace_tad_discuss_board":
  replace_tad_discuss_board();
  header("location: {$_SERVER['PHP_SELF']}");
  break;

  //新增資料
  case "insert_tad_discuss_board":
  $BoardID=insert_tad_discuss_board($_POST['BoardTitle']);
  header("location: {$_SERVER['PHP_SELF']}?BoardID=$BoardID");
  break;

  //更新資料
  case "update_tad_discuss_board":
  update_tad_discuss_board($BoardID);
  header("location: {$_SERVER['PHP_SELF']}");
  break;

  //輸入表格
  case "tad_discuss_board_form":
  tad_discuss_board_form($BoardID);
  break;

  //刪除資料
  case "delete_tad_discuss_board":
  delete_tad_discuss_board($BoardID);
  header("location: {$_SERVER['PHP_SELF']}");
  break;

  case "moveToBoardID":
  moveToBoardID($BoardID,$NewBoardID);
  header("location: {$_SERVER['PHP_SELF']}");
  break;

  case "changeBoardStatus":
  changeBoardStatus($BoardID,$_GET['act']);
  header("location: {$_SERVER['PHP_SELF']}");
  break;


  //預設動作
  default:
  if(empty($BoardID)){
    list_tad_discuss_board();
  }else{
    header("location: ../discuss.php?BoardID=$BoardID");
  }
  break;


  /*---判斷動作請貼在上方---*/
}

/*-----------秀出結果區--------------*/
include_once 'footer.php';

?>