<?php

const RES_CODE_SUCCESS          = 0;    //请求成功返回码；
const RES_CODE_ERROR            = 1;    //请求失败返回码；
const RES_CODE_NOTOKEN          = 1000; //缺少访问凭据；
const RES_CODE_INVALID_TOKEN    = 1001; //无效访问凭据；
const RES_CODE_NO_LOGIN         = 1003; //用户未登录；
const RES_CODE_NO_INFO          = 1004; //用户未完善信息；

const SESSION_SOURCE        = 'scopeSource';          //全局来源：admin：后台：wePub；微信公众号；webPc
const SESSION_COMPANY_ID    = 'scopeCompanyId';       //全局公司id
const SESSION_COMPANY_KEY   = 'scopeCompanyKey';      //全局公司key
const SESSION_DEPT_ID       = 'scopeDeptId';          //全局部门id
const SESSION_USER_ID       = 'scopeUserId';          //全局用户id
const SESSION_OPENID        = 'myOpenid';             //session openid的名称

const FR_COL_TYPE_UPLIMAGE      = 'uplimage';   //上传图片
const FR_COL_TYPE_UPLFILE       = 'uplfile';    //上传文件
//20220620递归处理：前向
//方向key
const DIRECTION     = 'DIRECTION';
//前向值
const DIRECT_PRE    = 'pre';
//后向值
const DIRECT_AFT    = 'after';
// 小程序appid
const WEAPPID       = 'wechatWeAppId';
//【资金来源微信】
const FR_FINANCE_WECHAT         = 'wechat';         //微信
const FR_FINANCE_MONEY          = 'money';          //余额:指存放在平台账户中的钱，类似电子钱包的功能
const FR_FINANCE_CMBSKT         = 'cmbSkt';         //招商银行收款通
const FR_FINANCE_WXWORK         = 'wxWork';         //企业微信

const HOST_BIND_ID              = 'hostBindId';

