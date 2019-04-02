<?php
  
    if(!defined("__XE__")) exit();

    if(!class_exists('NaverCaptcha', false)) 
    {

        class NaverCaptcha
        {
            var $client_id = "UGWzYGR_bPS1Bnu0W9MM";
            var $client_secret = "zw76lllcBu";
            var $target_acts = NULL;
            var $url;
            var $is_post = false;
            var $headers = array();
            private $key;

            private $addon_path;
            private $html;


        

            function setHeaders()
            {
                $this->headers[] = "X-Naver-Client-Id: ".$this->client_id;
                $this->headers[] = "X-Naver-Client-Secret: ".$this->client_secret;
            }

            function context() { return Context::getInstance(); }

            public function setPath($addon_path) { $this->addon_path = $addon_path; }

            function loadHtml() 
            {
                if (!$this->html)
                    $this->html = TemplateHandler::getInstance()->compile($this->addon_path . '/skin', 'view');
        
                return $this->html;
            }
        

            

            function before_module_proc()
            {

            }

            function before_module_init(&$ModuleHandler)
            {
                $logged_info = Context::get('logged_info');
                if($logged_info->is_admin == 'Y' || $logged_info->is_site_admin)
                {
                    return false;
                }

                if($_SESSION['captcha_authed'])
                {
                    return false;
                }

                $type = Context::get('captchaType');
                $value = Context:: get('captcha_value');
                $this->target_acts = array('procBoardInsertDocument', 'procBoardInsertComment', 'procIssuetrackerInsertIssue', 'procIssuetrackerInsertHistory', 'procTextyleInsertComment');

                if(Context::getRequestMethod() != 'XMLRPC' && Context::getRequestMethod() !== 'JSON')
                {
                    if($type == 'image')
                    {
                        if(!$this->compareCaptcha($_SESSION['captcha_keyword'], $value))
                        {
                            Context::loadLang(_XE_PATH_ . 'addons/captcha/lang');
                            $_SESSION['XE_VALIDATOR_ERROR'] = -1;
                            $_SESSION['XE_VALIDATOR_MESSAGE'] = Context::getLang('captcha_denied');
                            $_SESSION['XE_VALIDATOR_MESSAGE_TYPE'] = 'error';
                            $_SESSION['XE_VALIDATOR_RETURN_URL'] = Context::get('error_return_url');
                            $ModuleHandler->_setInputValueToSession();
                        }
                    }
                    else
                    {
                        Context::addHtmlHeader('<script>
                            if(!captchaTargetAct) {var captchaTargetAct = [];}
                            captchaTargetAct.push("' . implode('","', $this->target_acts) . '");
                            </script>');

                        Context::loadFile(array('./addons/naver_imgCaptcha/naver_imgCaptcha.js', 'body', '', null), true);
                    }
                }

                // $this->getCaptchaKey();
                // $this->getCaptchaImage();
                // $this->compareCaptcha($this->key, "1234");

                // compare session when calling actions such as writing a post or a comment on the board/issue tracker module
                if(!$_SESSION['captcha_authed'] && in_array(Context::get('act'), $this->target_acts))
                {
                    // Context::loadLang(_XE_PATH_ . 'addons/captcha/lang');
                    $ModuleHandler->error = "captcha_denied";
                }

                return true;
            }

            function before_module_init_getHtml() {
                if ($_SESSION['captcha_authed']) {
                    return false;
                }
        
                // $this->loadLang();
        
                header("Content-Type: text/xml; charset=UTF-8");
                header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
                header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
                header("Cache-Control: no-store, no-cache, must-revalidate");
                header("Cache-Control: post-check=0, pre-check=0", false);
                header("Pragma: no-cache");
                $this->getCaptchaKey();
                // $this->getCaptchaImage($_SESSION['captcha_keyword']);

                printf(file_get_contents($this->addon_path . '/tpl/response.view.xml'), $this->loadHtml(), $_SESSION['captcha_keyword']);
        
                $this->context()->close();
                exit();
            }

            function before_module_init_captchaImage()
            {
                if($_SESSION['captcha_authed'])
                {
                    return false;
                }

                // if(Context::get('renew'))
                // {
                //     $this->createKeyword();
                // }
    
                $keyword = $_SESSION['captcha_keyword'];
                
                $im = $this->getCaptchaImage($keyword);
    
                echo $im;    
                Context::close();
                exit();
            }

            function before_module_init_setCaptchaSession() {
                if($_SESSION['captcha_authed'])
                {
                    return false;
                }

                $this->getCaptchaKey();
                header("Content-Type: text/xml; charset=UTF-8");
                header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
                header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
                header("Cache-Control: no-store, no-cache, must-revalidate");
                header("Cache-Control: post-check=0, pre-check=0", false);
                header("Pragma: no-cache");
                printf("<response>\r\n <error>0</error>\r\n <message>success</message>\r\n <about_captcha><![CDATA[%s]]></about_captcha>\r\n <captcha_reload><![CDATA[%s]]></captcha_reload>\r\n <captcha_play><![CDATA[%s]]></captcha_play>\r\n <cmd_input><![CDATA[%s]]></cmd_input>\r\n <cmd_cancel><![CDATA[%s]]></cmd_cancel>\r\n </response>"
                        , Context::getLang('about_captcha')
                        , Context::getLang('captcha_reload')
                        , Context::getLang('captcha_play')
                        , Context::getLang('cmd_input')
                        , Context::getLang('cmd_cancel')
                );
                Context::close();
                exit();


            }





            function curlInit($url) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, $this->is_post);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
                return $ch;
            }

            function getCaptchaKey() {
                $code = "0";
                $url = "https://openapi.naver.com/v1/captcha/nkey?code=".$code;
                $ch = $this->curlInit($url);
                $response = curl_exec ($ch);
                $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close ($ch);                

                if($status_code == 200) {
                    $_SESSION['captcha_keyword'] = json_decode($response, true)['key'];
                } else {
                    echo "Error 내용:".$response;
                }
            }

            function getCaptchaImage($key) {
                $url = "https://openapi.naver.com/v1/captcha/ncaptcha.bin?key=".$key;
                $ch = $this->curlInit($url);
                $response = curl_exec ($ch);
                $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close ($ch);
                
                if($status_code == 200) {
                    //echo $response;
                    // $fp = fopen("captcha.jpg", "w+");
                    // fwrite($fp, $response);
                    // fclose($fp);
                    // imagepng($response);
                    // imagedestroy($response);
                    // echo "<img src='captcha.jpg'>";
                    return $response;
                } else {
                    echo "Error 내용:".$response;
                }                
            }

            function compareCaptcha($key, $value) {
                $code = "1";
                $url = "https://openapi.naver.com/v1/captcha/nkey?code=".$code."&key=".$key."&value=".$value;
                $ch = $this->curlInit($url);
                $response = curl_exec ($ch);
                $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
                curl_close ($ch);

                if($status_code == 200) 
                {
                    echo $value;      
                    echo $response;     
                    echo $_SESSION['captcha_keyword'];

                    $result = json_decode($response, true)['result'];
                    if ($result)
                    {
                        $_SESSION['captcha_authed'] = true;
                        return true; 
                    }
                    else {
                        unset($_SESSION['captcha_authed']);
                        return false;
                    }
                } else {
                    echo "Error 내용:".$response;
                }
            }
        }

        $GLOBALS['__NaverCaptcha__'] = new NaverCaptcha;
        $GLOBALS['__NaverCaptcha__']->setHeaders();
        $GLOBALS['__NaverCaptcha__']->setPath(_XE_PATH_.'addons/naver_imgCaptcha');
        Context::set('oNaverCaptcha', $GLOBALS['__NaverCaptcha__']);
    }

    $oAddonNaverCaptcha = &$GLOBALS['__NaverCaptcha__'];
   
    if(method_exists($oAddonNaverCaptcha, $called_position))
    {
        if(!call_user_func_array(array(&$oAddonNaverCaptcha, $called_position), array(&$this)))
        {
            return false;
        }       
        
    }

    $addon_act = Context::get('captcha_action');
    if($addon_act && method_exists($oAddonNaverCaptcha, $called_position . '_' . $addon_act))
    {
        if(!call_user_func_array(array(&$oAddonNaverCaptcha, $called_position . '_' . $addon_act), array(&$this)))
        {
            return false;
        }
    }

/* End of file captcha.addon.php */
/* Location: ./addons/captcha/captcha.addon.php */
