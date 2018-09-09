<?php

namespace lib\includes\oss;

use lib\includes\Init;
use lib\includes\Aes;

/** 
  * @param oss
  * @date 2017/10/18
  * @author ssp
  */

class Oss
{

    public function __construct() 
    {
        global $_G,$lib_config;
        $this->uid = $_G['uid'];
        $this->lib_config = $lib_config;
    }

    public function getPayTabImage($id)
    {
        header('Content-Type: image/png');

        global $_G;

        if( !$ids = $this->checkUser($id) )  Init::_exit(file_get_contents($this->lib_config['default_img_url']));

        $tableid = 'aid:'.$ids[1];
        $attachment = \C::t('forum_attachment_n')->fetch($tableid, $ids[1]);

        $imgUrl = sprintf($this->lib_config['oss_url'], $_G['config']['jitashe']['server']['atturl'], $attachment['attachment'], $ids[2]);

        Init::_exit(file_get_contents($imgUrl));

    }

    public function checkUser($cipher)
    {
        if( !Init::checkNotEmpty($this->uid) ) return false;

        $aes = new Aes($this->lib_config['aes_key']);
        $ids = $aes->decrypt($cipher);
        $ids = explode("#", $ids);

        if($ids[0] != $this->uid) return false;

        $tid = \C::t('forum_attachment')->fetch_field($ids[1], 'tid');
        $count = \C::t('common_credit_log')->count_by_search($this->uid, 'BTB', 0, 0, 0, 0, [], $tid);
        if(!$count) return false;

        return $ids;

    }

}