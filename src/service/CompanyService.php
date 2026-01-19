<?php

namespace xjryanse\phplite\service;

use xjryanse\phplite\facade\Session;
use xjryanse\phplite\logic\Arrays;
use xjryanse\servicesdk\entry\EntrySdk;
/**
 * 字段逻辑
 */
class CompanyService {
    
    /**
     * 替代 SystemCompany::sessionInit($this->comKey);
     * @param type $comKey
     */
    public static function sessionInit($comKey){
        // 公司key
        Session::set(SESSION_COMPANY_KEY, $comKey);
        $info       = EntrySdk::companyKeyInfo($key);
        $companyId  = Arrays::value($info, 'id');
        // 公司id
        Session::set(SESSION_COMPANY_ID, $companyId);
    }
    
}
