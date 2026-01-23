<?php

namespace xjryanse\phplite\service;

use xjryanse\phplite\session\RedisSession;
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
        // 公司key（完美写法：2026年1月22日）
        RedisSession::current()->set(SESSION_COMPANY_KEY, $comKey);
        $info       = EntrySdk::companyKeyInfo($comKey);
        $companyId  = Arrays::value($info, 'id');
        // （完美写法：2026年1月22日）
        RedisSession::current()->set(SESSION_COMPANY_ID, $companyId);
    }

}
