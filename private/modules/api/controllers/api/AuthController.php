<?php

namespace SMXD\Api\Controllers\API;

use LightSaml\Model\Protocol\AuthnRequest;
use Phalcon\Di;
use SMXD\Api\Controllers\ModuleApiController;
use SMXD\App\Models\UserLogin;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\CognitoAppHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\SamlHelper;
use SMXD\Application\Models\ApplicationModel;
use SMXD\Application\Models\ConstantExt;
use SMXD\Application\Models\SsoIdpConfigExt;
use SMXD\Application\Models\SupportedLanguageExt;
use SMXD\Application\Models\UserAuthorKeyExt;
use SMXD\Application\Models\UserLoginExt;
use SMXD\Application\Models\UserLoginSsoExt;

/**
 * Concrete implementation of Api module controller
 *
 * @RoutePrefix("/api/api")
 */
class AuthController extends ModuleApiController
{
    /**
     * @Route("/index", paths={module="api"}, methods={"GET"}, name="api-index-index")
     */
    public function indexAction()
    {

    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function addonAction()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Max-Age: 1000');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type');

        $this->checkPrelightRequest();

        $this->view->disable();
        $req = $this->request;
        $token = $req->getPost('_t'); // Token key of user

        // Find user by token key
        $userProfile = UserAuthorKeyExt::__findUserByAddonKey($token);
        if ($userProfile) {
            $return = [
                'success' => true,
                'msg' => 'Authorized'
            ];

        } else {
            $return = [
                'success' => false,
                'token' => $token,
                'msg' => 'Invalid key'
            ];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function errorAction()
    {
        $this->view->disable();
        $this->response->setJsonContent(['success' => false]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function ssoCallbackAction()
    {
        $suffix = getenv('APP_SUFFIX');
        $header = '*.' . $suffix;
        header('Access-Control-Allow-Origin: ' . $header);
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Max-Age: 1000');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type');

        $this->checkPrelightRequest();
        $this->view->disable();

        $detect = new \Mobile_Detect();
        $isMob = $detect->isMobile();

        $samlResponse = Helpers::__getRequestValue('SAMLResponse');
        //test of ADFS
//        $samlResponse = "PD94bWwgdmVyc2lvbj0iMS4wIj8+DQo8c2FtbHA6UmVzcG9uc2UgeG1sbnM6c2FtbHA9InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDpwcm90b2NvbCIgSUQ9Il82ZWYzOTk3Yy01MjliLTQ3NTQtOTU1OC05ZGVlMDEwMWQ5ODgiIFZlcnNpb249IjIuMCIgSXNzdWVJbnN0YW50PSIyMDIxLTA4LTE4VDExOjQzOjI3Ljk4MFoiIERlc3RpbmF0aW9uPSJodHRwczovL3RoaW5oZGV2LnJlbG9kYXkuY29tL2FwaS9hdXRoL3Nzb0NhbGxiYWNrIiBDb25zZW50PSJ1cm46b2FzaXM6bmFtZXM6dGM6U0FNTDoyLjA6Y29uc2VudDp1bnNwZWNpZmllZCIgSW5SZXNwb25zZVRvPSJfZDcxM2QzYWFmMzA5NGQ1NDg2NjM0NTBhMmY2ZDRmMWNiZjAxNjE4MWZmIj4NCiAgPElzc3VlciB4bWxucz0idXJuOm9hc2lzOm5hbWVzOnRjOlNBTUw6Mi4wOmFzc2VydGlvbiI+aHR0cDovL2FkZnMuanVsaXVzYmFlci5jb20vYWRmcy9zZXJ2aWNlcy90cnVzdDwvSXNzdWVyPg0KICA8c2FtbHA6U3RhdHVzPg0KICAgIDxzYW1scDpTdGF0dXNDb2RlIFZhbHVlPSJ1cm46b2FzaXM6bmFtZXM6dGM6U0FNTDoyLjA6c3RhdHVzOlN1Y2Nlc3MiLz4NCiAgPC9zYW1scDpTdGF0dXM+DQogIDxBc3NlcnRpb24geG1sbnM9InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDphc3NlcnRpb24iIElEPSJfYzRlNWZmNjctODk3MS00OWJhLWIyZGEtYzdlMzRlNjNjNjkyIiBJc3N1ZUluc3RhbnQ9IjIwMjEtMDgtMThUMTE6NDM6MjcuOTY0WiIgVmVyc2lvbj0iMi4wIj4NCiAgICA8SXNzdWVyPmh0dHA6Ly9hZGZzLmp1bGl1c2JhZXIuY29tL2FkZnMvc2VydmljZXMvdHJ1c3Q8L0lzc3Vlcj4NCiAgICA8ZHM6U2lnbmF0dXJlIHhtbG5zOmRzPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwLzA5L3htbGRzaWcjIj4NCiAgICAgIDxkczpTaWduZWRJbmZvPg0KICAgICAgICA8ZHM6Q2Fub25pY2FsaXphdGlvbk1ldGhvZCBBbGdvcml0aG09Imh0dHA6Ly93d3cudzMub3JnLzIwMDEvMTAveG1sLWV4Yy1jMTRuIyIvPg0KICAgICAgICA8ZHM6U2lnbmF0dXJlTWV0aG9kIEFsZ29yaXRobT0iaHR0cDovL3d3dy53My5vcmcvMjAwMS8wNC94bWxkc2lnLW1vcmUjcnNhLXNoYTI1NiIvPg0KICAgICAgICA8ZHM6UmVmZXJlbmNlIFVSST0iI19jNGU1ZmY2Ny04OTcxLTQ5YmEtYjJkYS1jN2UzNGU2M2M2OTIiPg0KICAgICAgICAgIDxkczpUcmFuc2Zvcm1zPg0KICAgICAgICAgICAgPGRzOlRyYW5zZm9ybSBBbGdvcml0aG09Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvMDkveG1sZHNpZyNlbnZlbG9wZWQtc2lnbmF0dXJlIi8+DQogICAgICAgICAgICA8ZHM6VHJhbnNmb3JtIEFsZ29yaXRobT0iaHR0cDovL3d3dy53My5vcmcvMjAwMS8xMC94bWwtZXhjLWMxNG4jIi8+DQogICAgICAgICAgPC9kczpUcmFuc2Zvcm1zPg0KICAgICAgICAgIDxkczpEaWdlc3RNZXRob2QgQWxnb3JpdGhtPSJodHRwOi8vd3d3LnczLm9yZy8yMDAxLzA0L3htbGVuYyNzaGEyNTYiLz4NCiAgICAgICAgICA8ZHM6RGlnZXN0VmFsdWU+U3hUOVlWTGYzaGJoUzBCaUIrM0dtNndQa1V0TWoveitpNjNmMnZwMnF4ST08L2RzOkRpZ2VzdFZhbHVlPg0KICAgICAgICA8L2RzOlJlZmVyZW5jZT4NCiAgICAgIDwvZHM6U2lnbmVkSW5mbz4NCiAgICAgIDxkczpTaWduYXR1cmVWYWx1ZT5lbWErcGVwdkhXRGJleURDdWUxQ1ljbjlxdEhma1ExTXFIYkI0RU1LRUg4ZlMxY2pFSGtTNUpIay9GVXFRMFlPODk0RWNjb3B1eG1WQ3JCMkVudHdneXdhVnZ1VEgvTzI3RVJ4ekJiRnlvdys1SDNpUUlBdUQwcGpzN0Z2ejc3ZVpYNVlCSVp4UkNoVC90UTU0OEhaeUFVUTd6U28xVE1UOVZUQzd4MG1uZDIyc1hZcnNDTEd0SkpvVXphV0ZURDRROWNBWUdlenV4NUtXSDVFWVU4WlNHaFduVmVCOTJhUElYeFlyQzFldnV1MHkwTE5EaW9zSjlVWjN3N0JlZ01PR3BnaVdBL0MrNUp2M0tqaENEYkdPOU9pQW5uMjFFU0xNdVZkamE5Rzc2ZEdqNUtPL0xCQk5aaFkvSUgvQVJ1YSs0T2p1WTVUTXpQV0xHWStZSEI2cGc9PTwvZHM6U2lnbmF0dXJlVmFsdWU+DQogICAgICA8S2V5SW5mbyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC8wOS94bWxkc2lnIyI+DQogICAgICAgIDxkczpYNTA5RGF0YT4NCiAgICAgICAgICA8ZHM6WDUwOUNlcnRpZmljYXRlPk1JSUM0akNDQWNxZ0F3SUJBZ0lRVkZOenFWSTBVWk5GdkZxSW9aZU42akFOQmdrcWhraUc5dzBCQVFzRkFEQXRNU3N3S1FZRFZRUURFeUpCUkVaVElGTnBaMjVwYm1jZ0xTQmhaR1p6TG1wMWJHbDFjMkpoWlhJdVkyOXRNQjRYRFRJeE1EVXpNVEV6TkRjeE5sb1hEVEl5TURVek1URXpORGN4Tmxvd0xURXJNQ2tHQTFVRUF4TWlRVVJHVXlCVGFXZHVhVzVuSUMwZ1lXUm1jeTVxZFd4cGRYTmlZV1Z5TG1OdmJUQ0NBU0l3RFFZSktvWklodmNOQVFFQkJRQURnZ0VQQURDQ0FRb0NnZ0VCQUxER1N0YTh6YUNlaHVFZm1qZFQ5a01kZDJLNHVSaXlOL3dEVXJkQnJvTHRPVVp3UlBnZ2s3SVBjb1BaYnVSZGJ2d1RKcFZORGQ5N3JMS3hURGQ1TGpYNzhaQ0ROblNERzY1dXRqMS9HMkFIci91R1VSMnhCb3E0aTd3Ym5XNEx5MFFkTE1veXFVNWNQQVg2ZmtwVk45Z1JXeGkvRGVraEdvSHpmQlQyRGhiRWRDZWFVLzJPQ2lnZU9IZ0dOK2pSUGp0dU1vNm5FVHlNcTVuaVc2K2tpREl2SSt6MmRRRFBBQ0s1dUlHUDY2TnlBYTRjYmtNUGRKSFIydHdGb2g1RGVwWFFjV0luT1owSkV0SmF3QWY5M0YybUVWZks0Ui9YRHVYVy9kUGd6bVhWdFFxT0ZnQTVCbkVabzFOblhybmFtTWFLM1lCdDBBSHNnQmlSeksyYmg1RUNBd0VBQVRBTkJna3Foa2lHOXcwQkFRc0ZBQU9DQVFFQWVBR0o0L0lKazAzWWRlQ1UrVVltZTVrZXcyYUdoQVJDc0VFMGYvKytmayt6SFhOME1weFFESHZFc0tuZXBMQWN0dnM5azljUmdGVlE4cXYzMDlhd1drZ3JkbmNSZC9HRVdMYWRjMDhpdk9OcXhleXB1clUyc1BicnV5c3VUSGRmbGtteEo1ckUyQnAxUzRZUnhRYXdVZjVnN0pUeXFsSEdCYUc0d0Q4c2gwcHVCQjdwRTJ6Zk4zd2hiQzVtaDZhRmxWVmNuYW85NnhIQ21xWm0vQVJ3UTltVUZIcnRWeEdDTktveGJhSE9rQWMrdkdPTUMzdFN5N0QzQXlRU2hVRGZsVTdxSENYN3lLVHgyN2g5eDk4bmZzNk9NdExXRW14YzYrYmZjaXRva0lLYmtDVk1JNDFVSjE2S3BQN210ckU3N0REa1lMUVBaZ0NtNGR5bXoyM0k4dz09PC9kczpYNTA5Q2VydGlmaWNhdGU+DQogICAgICAgIDwvZHM6WDUwOURhdGE+DQogICAgICA8L0tleUluZm8+DQogICAgPC9kczpTaWduYXR1cmU+DQogICAgPFN1YmplY3Q+DQogICAgICA8TmFtZUlEPnlvcmRhbi5rbGVjaGVyb3ZAanVsaXVzYmFlci5jb208L05hbWVJRD4NCiAgICAgIDxTdWJqZWN0Q29uZmlybWF0aW9uIE1ldGhvZD0idXJuOm9hc2lzOm5hbWVzOnRjOlNBTUw6Mi4wOmNtOmJlYXJlciI+DQogICAgICAgIDxTdWJqZWN0Q29uZmlybWF0aW9uRGF0YSBJblJlc3BvbnNlVG89Il9kNzEzZDNhYWYzMDk0ZDU0ODY2MzQ1MGEyZjZkNGYxY2JmMDE2MTgxZmYiIE5vdE9uT3JBZnRlcj0iMjAyMS0wOC0xOFQxMTo0ODoyNy45ODBaIiBSZWNpcGllbnQ9Imh0dHBzOi8vdGhpbmhkZXYucmVsb2RheS5jb20vYXBpL2F1dGgvc3NvQ2FsbGJhY2siLz4NCiAgICAgIDwvU3ViamVjdENvbmZpcm1hdGlvbj4NCiAgICA8L1N1YmplY3Q+DQogICAgPENvbmRpdGlvbnMgTm90QmVmb3JlPSIyMDIxLTA4LTE4VDExOjQzOjI3Ljk2NFoiIE5vdE9uT3JBZnRlcj0iMjAyMS0wOC0xOFQxMjo0MzoyNy45NjRaIj4NCiAgICAgIDxBdWRpZW5jZVJlc3RyaWN0aW9uPg0KICAgICAgICA8QXVkaWVuY2U+aHR0cHM6Ly90aGluaGRldi1mcm9udGVuZC5yZWxvZGF5LmNvbS9ocjwvQXVkaWVuY2U+DQogICAgICA8L0F1ZGllbmNlUmVzdHJpY3Rpb24+DQogICAgPC9Db25kaXRpb25zPg0KICAgIDxBdHRyaWJ1dGVTdGF0ZW1lbnQ+DQogICAgICA8QXR0cmlidXRlIE5hbWU9ImVtYWlsIj4NCiAgICAgICAgPEF0dHJpYnV0ZVZhbHVlPnlvcmRhbi5rbGVjaGVyb3ZAanVsaXVzYmFlci5jb208L0F0dHJpYnV0ZVZhbHVlPg0KICAgICAgPC9BdHRyaWJ1dGU+DQogICAgPC9BdHRyaWJ1dGVTdGF0ZW1lbnQ+DQogICAgPEF1dGhuU3RhdGVtZW50IEF1dGhuSW5zdGFudD0iMjAyMS0wOC0xOFQxMTo0MzoyNy45MDJaIiBTZXNzaW9uSW5kZXg9Il9jNGU1ZmY2Ny04OTcxLTQ5YmEtYjJkYS1jN2UzNGU2M2M2OTIiPg0KICAgICAgPEF1dGhuQ29udGV4dD4NCiAgICAgICAgPEF1dGhuQ29udGV4dENsYXNzUmVmPmh0dHA6Ly9zY2hlbWFzLm1pY3Jvc29mdC5jb20vd3MvMjAxMi8xMi9hdXRobWV0aG9kL3Bob25lYXBwbm90aWZpY2F0aW9uPC9BdXRobkNvbnRleHRDbGFzc1JlZj4NCiAgICAgIDwvQXV0aG5Db250ZXh0Pg0KICAgIDwvQXV0aG5TdGF0ZW1lbnQ+DQogIDwvQXNzZXJ0aW9uPg0KPC9zYW1scDpSZXNwb25zZT4=";
        //test of Auth0 local
//        $samlResponse = "PHNhbWxwOlJlc3BvbnNlIHhtbG5zOnNhbWxwPSJ1cm46b2FzaXM6bmFtZXM6dGM6U0FNTDoyLjA6cHJvdG9jb2wiIElEPSJfY2ExMDdhMDhhNzhlY2ZjN2I1NGEiICBJblJlc3BvbnNlVG89Il84N2ZjNTlkNDhlOTFiMDlmOWM2NjcwYzMzODAwNTRmYWQzYjZjMDhjNjAiICBWZXJzaW9uPSIyLjAiIElzc3VlSW5zdGFudD0iMjAyMi0wNi0wMlQwNDozMjo0OC43ODdaIiAgRGVzdGluYXRpb249Imh0dHBzOi8vYXBpLnJlbG9kYXkubG9jYWwvYXBpL2F1dGgvc3NvQ2FsbGJhY2siPjxzYW1sOklzc3VlciB4bWxuczpzYW1sPSJ1cm46b2FzaXM6bmFtZXM6dGM6U0FNTDoyLjA6YXNzZXJ0aW9uIj51cm46cHJlcHJvZC1zYW1sLWlkcC51cy5hdXRoMC5jb208L3NhbWw6SXNzdWVyPjxzYW1scDpTdGF0dXM+PHNhbWxwOlN0YXR1c0NvZGUgVmFsdWU9InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDpzdGF0dXM6U3VjY2VzcyIvPjwvc2FtbHA6U3RhdHVzPjxzYW1sOkFzc2VydGlvbiB4bWxuczpzYW1sPSJ1cm46b2FzaXM6bmFtZXM6dGM6U0FNTDoyLjA6YXNzZXJ0aW9uIiBWZXJzaW9uPSIyLjAiIElEPSJfNDl3bzVBc2Y0N3ZDdnpGaVZHbTVTZ0ZPNDFRaE1qWGQiIElzc3VlSW5zdGFudD0iMjAyMi0wNi0wMlQwNDozMjo0OC43NDFaIj48c2FtbDpJc3N1ZXI+dXJuOnByZXByb2Qtc2FtbC1pZHAudXMuYXV0aDAuY29tPC9zYW1sOklzc3Vlcj48U2lnbmF0dXJlIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwLzA5L3htbGRzaWcjIj48U2lnbmVkSW5mbz48Q2Fub25pY2FsaXphdGlvbk1ldGhvZCBBbGdvcml0aG09Imh0dHA6Ly93d3cudzMub3JnLzIwMDEvMTAveG1sLWV4Yy1jMTRuIyIvPjxTaWduYXR1cmVNZXRob2QgQWxnb3JpdGhtPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwLzA5L3htbGRzaWcjcnNhLXNoYTEiLz48UmVmZXJlbmNlIFVSST0iI180OXdvNUFzZjQ3dkN2ekZpVkdtNVNnRk80MVFoTWpYZCI+PFRyYW5zZm9ybXM+PFRyYW5zZm9ybSBBbGdvcml0aG09Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvMDkveG1sZHNpZyNlbnZlbG9wZWQtc2lnbmF0dXJlIi8+PFRyYW5zZm9ybSBBbGdvcml0aG09Imh0dHA6Ly93d3cudzMub3JnLzIwMDEvMTAveG1sLWV4Yy1jMTRuIyIvPjwvVHJhbnNmb3Jtcz48RGlnZXN0TWV0aG9kIEFsZ29yaXRobT0iaHR0cDovL3d3dy53My5vcmcvMjAwMC8wOS94bWxkc2lnI3NoYTEiLz48RGlnZXN0VmFsdWU+aGlKNGN6SFVhb3N3U0s4d2Vid3FNN2lrc3A0PTwvRGlnZXN0VmFsdWU+PC9SZWZlcmVuY2U+PC9TaWduZWRJbmZvPjxTaWduYXR1cmVWYWx1ZT5iWEMxMnRMdjBycUI2dWxpdkIzdTZHYU1OdE1tdm02ZEhGZ2RJbUpMZjh5dE52d3EyS0d2K1RmTGJrZHJNUWRwY0dHYktvWGQxckpsYkZYWEtyU0prblJVdnNoV2xQOTR0YjhmTzMrdmNUUEFlWVI0dkFzd2F6dmExNWtoUzNPTTZZcm9CZzh4SjdESzUvRnUrazRmQVN6THk0eUd5UktrbGFpdU5lbGJKdEYreW1kVzk3RFY2UnBGMmZ1T2l5dDU2cVAzbUhZL2NHRktJNGg0b3BIZW0weTBaRnEycHp3Mzk1cXZDM3hPS2Z6L01pYlI4ZWtsYXJFZFlFSlVBS2w0OHIxaVZPM0NndHBKMVhFRHVhM1ZHYVJad3BwbWczYXVOcU5xUzlocmhEWVFyQzVianY4U0dYc2tkdkpXOWoxNy9hMU13a1ZHL1hwVDMwbEMvTjM1T0E9PTwvU2lnbmF0dXJlVmFsdWU+PEtleUluZm8+PFg1MDlEYXRhPjxYNTA5Q2VydGlmaWNhdGU+TUlJREZUQ0NBZjJnQXdJQkFnSUplRlJhMlhrNld0SnJNQTBHQ1NxR1NJYjNEUUVCQ3dVQU1DZ3hKakFrQmdOVkJBTVRIWEJ5WlhCeWIyUXRjMkZ0YkMxcFpIQXVkWE11WVhWMGFEQXVZMjl0TUI0WERUSXhNRGd3TXpBNU16RTBPVm9YRFRNMU1EUXhNakE1TXpFME9Wb3dLREVtTUNRR0ExVUVBeE1kY0hKbGNISnZaQzF6WVcxc0xXbGtjQzUxY3k1aGRYUm9NQzVqYjIwd2dnRWlNQTBHQ1NxR1NJYjNEUUVCQVFVQUE0SUJEd0F3Z2dFS0FvSUJBUUNoeWdLaTJIQTZvSGROSmNscmJaei9jcmtLZ2R0dzNYTVBkTDV4VFJKS0RudEJDUmNIS0docGVaTmxpNmNmZGFGS0xac094SG9KOUpYd2lpY0w0ZTk1U3VxeFNNYTI3bHRzdjNLR2VwaUdmVmFSM29zRWV5QTNlcndVM0VQNEMrei80M3FGQi9wY1dra3F3YUdWZzdYNS85eFlyc0pod0FwQkYvc0VhVkpaRXJTNW5nZnNZT0lDeEZoRzgzTDdrVHZLNFlTbGR0K0lYeDh5aVFkQ0dXZTgrSXk3K1ZLOFVsekd5L3dqalgzSVJIV3U1VUFLVGNiUWNFcFZIMFZacWc1ZzNPanpQUUxyV1pjVDAvVkVFWXRuSXZjaU9YZmlVSHd1bkpudFRseDc0NjZCVTZ5OFBqNzFJaEhveFZwUVBrclBmdXVGZnBUNnBzanZodGdyZ1JJQkFnTUJBQUdqUWpCQU1BOEdBMVVkRXdFQi93UUZNQU1CQWY4d0hRWURWUjBPQkJZRUZHZG5EVlUzZHpZT0EyeEx0VjZtQmVUS0M0NGRNQTRHQTFVZER3RUIvd1FFQXdJQ2hEQU5CZ2txaGtpRzl3MEJBUXNGQUFPQ0FRRUFTemdXZXlEOXIra1dLc0MwWVV3b0lHcDdMWmVCSGdjdStCR2d4MklHbmViWXE4cDUyMm5weHkyRjJYL1RPdm54U2kxT3BJc24raVVQWjQxSEI4OXM2dTlWV051WUczRGY3UjVTWTZzM1VEcUJ1OXdsZEtzaG1MOVduS1Z1aW43eUppUVE2WnlsT3hwcURZOXowMVYzeFpKYTIzWDYrZ1FpRDE0MXRlYmNvTUdZSUlyanpVc2dySWVoNHdOOS9vUklXVnM0dGRlNDdSRVhJTFhpRFNwbFNheHVoSVJDcXZzak1Xcm5SNGc3MDJSdlFLQ25JYkxtbmtKM2hlL3ZlWTE3MllZZ1djY1JMQ0J6YXdiNnA5ZktMTmJpQ3JzQUl0OXhGZ2p0RzhpVDZVaTlDcG8rYUlyMXNveU16cERRVDZMalVQMllmVmJEUThKM0Z0YzNoZzlhYkE9PTwvWDUwOUNlcnRpZmljYXRlPjwvWDUwOURhdGE+PC9LZXlJbmZvPjwvU2lnbmF0dXJlPjxzYW1sOlN1YmplY3Q+PHNhbWw6TmFtZUlEIEZvcm1hdD0idXJuOm9hc2lzOm5hbWVzOnRjOlNBTUw6MS4xOm5hbWVpZC1mb3JtYXQ6dW5zcGVjaWZpZWQiPmF1dGgwfDYxMGEwMTkxZTE2YjkxMDA2YWQ1MmZkODwvc2FtbDpOYW1lSUQ+PHNhbWw6U3ViamVjdENvbmZpcm1hdGlvbiBNZXRob2Q9InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDpjbTpiZWFyZXIiPjxzYW1sOlN1YmplY3RDb25maXJtYXRpb25EYXRhIE5vdE9uT3JBZnRlcj0iMjAyMi0wNi0wMlQwNTozMjo0OC43NDFaIiBSZWNpcGllbnQ9Imh0dHBzOi8vYXBpLnJlbG9kYXkubG9jYWwvYXBpL2F1dGgvc3NvQ2FsbGJhY2siIEluUmVzcG9uc2VUbz0iXzg3ZmM1OWQ0OGU5MWIwOWY5YzY2NzBjMzM4MDA1NGZhZDNiNmMwOGM2MCIvPjwvc2FtbDpTdWJqZWN0Q29uZmlybWF0aW9uPjwvc2FtbDpTdWJqZWN0PjxzYW1sOkNvbmRpdGlvbnMgTm90QmVmb3JlPSIyMDIyLTA2LTAyVDA0OjMyOjQ4Ljc0MVoiIE5vdE9uT3JBZnRlcj0iMjAyMi0wNi0wMlQwNTozMjo0OC43NDFaIj48c2FtbDpBdWRpZW5jZVJlc3RyaWN0aW9uPjxzYW1sOkF1ZGllbmNlPmh0dHBzOi8vY2xvdWQucmVsb3RhbGVudC5jb20vc3NvPC9zYW1sOkF1ZGllbmNlPjwvc2FtbDpBdWRpZW5jZVJlc3RyaWN0aW9uPjwvc2FtbDpDb25kaXRpb25zPjxzYW1sOkF1dGhuU3RhdGVtZW50IEF1dGhuSW5zdGFudD0iMjAyMi0wNi0wMlQwNDozMjo0OC43NDFaIiBTZXNzaW9uSW5kZXg9Il95UlZ5YXg2WHZuc2UzU3g1UTFLbVpZRzlkMnhGdmxXSiI+PHNhbWw6QXV0aG5Db250ZXh0PjxzYW1sOkF1dGhuQ29udGV4dENsYXNzUmVmPnVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDphYzpjbGFzc2VzOnVuc3BlY2lmaWVkPC9zYW1sOkF1dGhuQ29udGV4dENsYXNzUmVmPjwvc2FtbDpBdXRobkNvbnRleHQ+PC9zYW1sOkF1dGhuU3RhdGVtZW50PjxzYW1sOkF0dHJpYnV0ZVN0YXRlbWVudCB4bWxuczp4cz0iaHR0cDovL3d3dy53My5vcmcvMjAwMS9YTUxTY2hlbWEiIHhtbG5zOnhzaT0iaHR0cDovL3d3dy53My5vcmcvMjAwMS9YTUxTY2hlbWEtaW5zdGFuY2UiPjxzYW1sOkF0dHJpYnV0ZSBOYW1lPSJodHRwOi8vc2NoZW1hcy54bWxzb2FwLm9yZy93cy8yMDA1LzA1L2lkZW50aXR5L2NsYWltcy9uYW1laWRlbnRpZmllciIgTmFtZUZvcm1hdD0idXJuOm9hc2lzOm5hbWVzOnRjOlNBTUw6Mi4wOmF0dHJuYW1lLWZvcm1hdDp1cmkiPjxzYW1sOkF0dHJpYnV0ZVZhbHVlIHhzaTp0eXBlPSJ4czpzdHJpbmciPmF1dGgwfDYxMGEwMTkxZTE2YjkxMDA2YWQ1MmZkODwvc2FtbDpBdHRyaWJ1dGVWYWx1ZT48L3NhbWw6QXR0cmlidXRlPjxzYW1sOkF0dHJpYnV0ZSBOYW1lPSJodHRwOi8vc2NoZW1hcy54bWxzb2FwLm9yZy93cy8yMDA1LzA1L2lkZW50aXR5L2NsYWltcy9lbWFpbGFkZHJlc3MiIE5hbWVGb3JtYXQ9InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDphdHRybmFtZS1mb3JtYXQ6dXJpIj48c2FtbDpBdHRyaWJ1dGVWYWx1ZSB4c2k6dHlwZT0ieHM6c3RyaW5nIj5lZHVhcmRhQHBldHJvYnJhcy5jb208L3NhbWw6QXR0cmlidXRlVmFsdWU+PC9zYW1sOkF0dHJpYnV0ZT48c2FtbDpBdHRyaWJ1dGUgTmFtZT0iaHR0cDovL3NjaGVtYXMueG1sc29hcC5vcmcvd3MvMjAwNS8wNS9pZGVudGl0eS9jbGFpbXMvbmFtZSIgTmFtZUZvcm1hdD0idXJuOm9hc2lzOm5hbWVzOnRjOlNBTUw6Mi4wOmF0dHJuYW1lLWZvcm1hdDp1cmkiPjxzYW1sOkF0dHJpYnV0ZVZhbHVlIHhzaTp0eXBlPSJ4czpzdHJpbmciPmVkdWFyZGFAcGV0cm9icmFzLmNvbTwvc2FtbDpBdHRyaWJ1dGVWYWx1ZT48L3NhbWw6QXR0cmlidXRlPjxzYW1sOkF0dHJpYnV0ZSBOYW1lPSJodHRwOi8vc2NoZW1hcy54bWxzb2FwLm9yZy93cy8yMDA1LzA1L2lkZW50aXR5L2NsYWltcy91cG4iIE5hbWVGb3JtYXQ9InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDphdHRybmFtZS1mb3JtYXQ6dXJpIj48c2FtbDpBdHRyaWJ1dGVWYWx1ZSB4c2k6dHlwZT0ieHM6c3RyaW5nIj5lZHVhcmRhQHBldHJvYnJhcy5jb208L3NhbWw6QXR0cmlidXRlVmFsdWU+PC9zYW1sOkF0dHJpYnV0ZT48c2FtbDpBdHRyaWJ1dGUgTmFtZT0iaHR0cDovL3NjaGVtYXMuYXV0aDAuY29tL2lkZW50aXRpZXMvZGVmYXVsdC9jb25uZWN0aW9uIiBOYW1lRm9ybWF0PSJ1cm46b2FzaXM6bmFtZXM6dGM6U0FNTDoyLjA6YXR0cm5hbWUtZm9ybWF0OnVyaSI+PHNhbWw6QXR0cmlidXRlVmFsdWUgeHNpOnR5cGU9InhzOnN0cmluZyI+VXNlcm5hbWUtUGFzc3dvcmQtQXV0aGVudGljYXRpb248L3NhbWw6QXR0cmlidXRlVmFsdWU+PC9zYW1sOkF0dHJpYnV0ZT48c2FtbDpBdHRyaWJ1dGUgTmFtZT0iaHR0cDovL3NjaGVtYXMuYXV0aDAuY29tL2lkZW50aXRpZXMvZGVmYXVsdC9wcm92aWRlciIgTmFtZUZvcm1hdD0idXJuOm9hc2lzOm5hbWVzOnRjOlNBTUw6Mi4wOmF0dHJuYW1lLWZvcm1hdDp1cmkiPjxzYW1sOkF0dHJpYnV0ZVZhbHVlIHhzaTp0eXBlPSJ4czpzdHJpbmciPmF1dGgwPC9zYW1sOkF0dHJpYnV0ZVZhbHVlPjwvc2FtbDpBdHRyaWJ1dGU+PHNhbWw6QXR0cmlidXRlIE5hbWU9Imh0dHA6Ly9zY2hlbWFzLmF1dGgwLmNvbS9pZGVudGl0aWVzL2RlZmF1bHQvaXNTb2NpYWwiIE5hbWVGb3JtYXQ9InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDphdHRybmFtZS1mb3JtYXQ6dXJpIj48c2FtbDpBdHRyaWJ1dGVWYWx1ZSB4c2k6dHlwZT0ieHM6Ym9vbGVhbiI+ZmFsc2U8L3NhbWw6QXR0cmlidXRlVmFsdWU+PC9zYW1sOkF0dHJpYnV0ZT48c2FtbDpBdHRyaWJ1dGUgTmFtZT0iaHR0cDovL3NjaGVtYXMuYXV0aDAuY29tL2NsaWVudElEIiBOYW1lRm9ybWF0PSJ1cm46b2FzaXM6bmFtZXM6dGM6U0FNTDoyLjA6YXR0cm5hbWUtZm9ybWF0OnVyaSI+PHNhbWw6QXR0cmlidXRlVmFsdWUgeHNpOnR5cGU9InhzOnN0cmluZyI+VzFBTkdQMm4wZU1EUHJqRzVlOE5vVmlYcGllMU91VkY8L3NhbWw6QXR0cmlidXRlVmFsdWU+PC9zYW1sOkF0dHJpYnV0ZT48c2FtbDpBdHRyaWJ1dGUgTmFtZT0iaHR0cDovL3NjaGVtYXMuYXV0aDAuY29tL2NyZWF0ZWRfYXQiIE5hbWVGb3JtYXQ9InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDphdHRybmFtZS1mb3JtYXQ6dXJpIj48c2FtbDpBdHRyaWJ1dGVWYWx1ZSB4c2k6dHlwZT0ieHM6YW55VHlwZSI+V2VkIEF1ZyAwNCAyMDIxIDAyOjU1OjEzIEdNVCswMDAwIChDb29yZGluYXRlZCBVbml2ZXJzYWwgVGltZSk8L3NhbWw6QXR0cmlidXRlVmFsdWU+PC9zYW1sOkF0dHJpYnV0ZT48c2FtbDpBdHRyaWJ1dGUgTmFtZT0iaHR0cDovL3NjaGVtYXMuYXV0aDAuY29tL2VtYWlsX3ZlcmlmaWVkIiBOYW1lRm9ybWF0PSJ1cm46b2FzaXM6bmFtZXM6dGM6U0FNTDoyLjA6YXR0cm5hbWUtZm9ybWF0OnVyaSI+PHNhbWw6QXR0cmlidXRlVmFsdWUgeHNpOnR5cGU9InhzOmJvb2xlYW4iPmZhbHNlPC9zYW1sOkF0dHJpYnV0ZVZhbHVlPjwvc2FtbDpBdHRyaWJ1dGU+PHNhbWw6QXR0cmlidXRlIE5hbWU9Imh0dHA6Ly9zY2hlbWFzLmF1dGgwLmNvbS9uaWNrbmFtZSIgTmFtZUZvcm1hdD0idXJuOm9hc2lzOm5hbWVzOnRjOlNBTUw6Mi4wOmF0dHJuYW1lLWZvcm1hdDp1cmkiPjxzYW1sOkF0dHJpYnV0ZVZhbHVlIHhzaTp0eXBlPSJ4czpzdHJpbmciPmVkdWFyZGE8L3NhbWw6QXR0cmlidXRlVmFsdWU+PC9zYW1sOkF0dHJpYnV0ZT48c2FtbDpBdHRyaWJ1dGUgTmFtZT0iaHR0cDovL3NjaGVtYXMuYXV0aDAuY29tL3BpY3R1cmUiIE5hbWVGb3JtYXQ9InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDphdHRybmFtZS1mb3JtYXQ6dXJpIj48c2FtbDpBdHRyaWJ1dGVWYWx1ZSB4c2k6dHlwZT0ieHM6c3RyaW5nIj5odHRwczovL3MuZ3JhdmF0YXIuY29tL2F2YXRhci81YmNkOTczN2I1NGVkN2FjNzY3OTY4NDJmNGRiY2E4OT9zPTQ4MCZhbXA7cj1wZyZhbXA7ZD1odHRwcyUzQSUyRiUyRmNkbi5hdXRoMC5jb20lMkZhdmF0YXJzJTJGZWQucG5nPC9zYW1sOkF0dHJpYnV0ZVZhbHVlPjwvc2FtbDpBdHRyaWJ1dGU+PHNhbWw6QXR0cmlidXRlIE5hbWU9Imh0dHA6Ly9zY2hlbWFzLmF1dGgwLmNvbS91cGRhdGVkX2F0IiBOYW1lRm9ybWF0PSJ1cm46b2FzaXM6bmFtZXM6dGM6U0FNTDoyLjA6YXR0cm5hbWUtZm9ybWF0OnVyaSI+PHNhbWw6QXR0cmlidXRlVmFsdWUgeHNpOnR5cGU9InhzOmFueVR5cGUiPlRodSBKdW4gMDIgMjAyMiAwNDoyOToyMCBHTVQrMDAwMCAoQ29vcmRpbmF0ZWQgVW5pdmVyc2FsIFRpbWUpPC9zYW1sOkF0dHJpYnV0ZVZhbHVlPjwvc2FtbDpBdHRyaWJ1dGU+PC9zYW1sOkF0dHJpYnV0ZVN0YXRlbWVudD48L3NhbWw6QXNzZXJ0aW9uPjwvc2FtbHA6UmVzcG9uc2U+";

        if (!$samlResponse){
            $message = ConstantExt::__translateConstant('INVALID_SAML_RESPONSE_TEXT');
            $return = [
                'success' => false,
                'message' => $message
            ];
            goto end_of_function;
        }

        $decode = base64_decode($samlResponse);

        try {
            error_reporting(0);
            $samlRes = SamlHelper::__parseXmlFile($decode);

            $authnRequest = AuthnRequest::fromXML($decode, $samlRes);

            if ($authnRequest->getFirstAssertion() == null){
                $message = ConstantExt::__translateConstant('INVALID_SAML_RESPONSE_TEXT');
                $return = [
                    'success' => false,
                    'message' => $message
                ];
                goto end_of_function;
            }

            $ssoRequestId = $authnRequest->getInResponseTo();

            if($authnRequest->getFirstAssertion()->getSubject() !== null &&
                $authnRequest->getFirstAssertion()->getSubject()->getFirstSubjectConfirmation() !== null &&
                $authnRequest->getFirstAssertion()->getSubject()->getFirstSubjectConfirmation()->getSubjectConfirmationData() !== null &&
                $authnRequest->getFirstAssertion()->getSubject()->getFirstSubjectConfirmation()->getSubjectConfirmationData()->getNotOnOrAfterTimestamp() !== null){
                $notOnOrAfter = $authnRequest->getFirstAssertion()->getSubject()->getFirstSubjectConfirmation()->getSubjectConfirmationData()->getNotOnOrAfterTimestamp();
                if(time() > $notOnOrAfter){
                    $message = ConstantExt::__translateConstant('SAML_RESPONSE_TIMEOUT_TEXT');
                    $return = [
                        'success' => false,
                        'message' => $message
                    ];
                    goto end_of_function;
                }
            }



            $firstAttribute = $authnRequest->getFirstAssertion()->getFirstAttributeStatement();

            $emailObject = $firstAttribute->getFirstAttributeByName(SamlHelper::AUTH0_MAPPING_ATTRIBUTE['email']);

            if (!$emailObject) {
                $emailObject = $firstAttribute->getFirstAttributeByName(SamlHelper::MAPPING_ATTRIBUTE['email']);
            }

            $email = $emailObject->getFirstAttributeValue();


            if (!$email) {
                $return = [
                    'success' => false,
                    'message' => "Invalid login credentials"
                ];
                goto end_of_function;
            }
            $userLogin = UserLoginExt::findFirstByEmail($email);

            if (!$userLogin) {
                $return = [
                    'success' => false,
                    'message' => "Invalid login credentials"
                ];
                goto end_of_function;
            }

            //Check sso request id and user login


            $company = $userLogin->getEmployeeOrUserProfile()->getCompany();
            $ssoIdpConfig = $company->getSsoIdpConfig();

            if ($ssoIdpConfig){
                $verified = SamlHelper::__verifyAuthnRequest($authnRequest, $ssoIdpConfig->getSsoCertificate());
                if (!$verified['success']){
                    $return = $verified;
                    goto end_of_function;
                }
            }



            $di = Di::getDefault();
            $appConfig = $di->get('appConfig');
            $awsRegion = $appConfig->aws->awsCognitoRegion;
            CognitoAppHelper::__startCognitoClient($awsRegion);
            $cognitoLogin = CognitoAppHelper::__loginUserCognitoNoPassword($email);

            if ($cognitoLogin['success'] == false) {
                $resultUserSso = $cognitoLogin;
                $resultUserSso['region'] = CognitoAppHelper::__getRegion();
                $resultUserSso['errorType'] = 'cognitoError';
                goto end;
            }

            $loginDetail = $cognitoLogin['detail'];
            $userLoginSsoValid = $userLogin->getUserLoginSsoWithRequest($this->request->getClientAddress(), $ssoRequestId);

            if(!$userLoginSsoValid){
                $return = [
                    'success' => false,
                    'message' => "Invalid login credentials"
                ];
                goto end_of_function;
            }



            $uuid = $userLoginSsoValid->getUuid();
            $userLoginSsoValid->setAccessToken($loginDetail['AccessToken']);
            $userLoginSsoValid->setRefreshToken($loginDetail['RefreshToken']);
            $userLoginSsoValid->setSamlToken($samlResponse);
            if(!$userLoginSsoValid->getLifetime()){
                $userLoginSsoValid->setLifetime(time() + CacheHelper::__TIME_10_MINUTES);
            }

            $userLoginSsoValid->setIsAlive(UserLoginSsoExt::ALIVE_YES);
            $resultUserSso = $userLoginSsoValid->__quickUpdate();
            if ($resultUserSso['success'] == false) {
                goto end;
            }

//            $userLoginSsoValid = $userLogin->getValidUserLoginSso($this->request->getClientAddress());

//            if (!$userLoginSsoValid) {
//                $uuid = Helpers::__uuid();
//                $newUserLoginSso = new UserLoginSsoExt();
//                $newUserLoginSso->setUuid($uuid);
//                $newUserLoginSso->setUserLoginId($userLogin->getId());
//                $newUserLoginSso->setAccessToken($loginDetail['AccessToken']);
//                $newUserLoginSso->setRefreshToken($loginDetail['RefreshToken']);
//                $newUserLoginSso->setSamlToken($samlResponse);
//                $newUserLoginSso->setLifetime(time() + CacheHelper::__TIME_10_MINUTES);
//                $newUserLoginSso->setIpAddress($this->request->getClientAddress());
//                $newUserLoginSso->setIsAlive(UserLoginSsoExt::ALIVE_YES);
//
//                $resultUserSso = $newUserLoginSso->__quickCreate();
//                if ($resultUserSso['success'] == false) {
//                    goto end;
//                }
//            } else {
//                $uuid = $userLoginSsoValid->getUuid();
//            }

            if ($userLogin->isEmployee()) {
                $resultUpdateLastLogin = $userLogin->updateDateConnectedAt();
                if ($isMob) {
                    $redirectUrl = $userLogin->getEmployeeOrUserProfile()->getMobileAppUrl() . SamlHelper::EE_MOBILE_SAML_POSTFIX_URL . '/' . $uuid;
                } else {
                    $redirectUrl = $userLogin->getEmployeeOrUserProfile()->getAppUrl() . SamlHelper::EE_SAML_POSTFIX_URL . '/' . $uuid;
                }
            } else {
                $resultUpdateLastLogin = $userLogin->updateDateConnectedAt();
                $redirectUrl = $userLogin->getEmployeeOrUserProfile()->getAppUrl() . SamlHelper::DSP_HR_SAML_POSTFIX_URL . '/' . $uuid;
            }

            return $this->response->redirect($redirectUrl, true);

        } catch (\Exception $e) {
            $resultUserSso = [
                'success' => false,
                'message' => $e->getMessage()
            ];

            Helpers::__trackError($e);
            goto end;
        }

        end:

        if (isset($userLogin) && $userLogin && $userLogin->isEmployee()) {
            if ($isMob) {
                $errorUrl = $userLogin->getEmployeeOrUserProfile()->getMobileAppUrl() . SamlHelper::EE_MOBILE_SAML_INVALID_URL;
            } else {
                $errorUrl = $userLogin->getEmployeeOrUserProfile()->getAppUrl() . SamlHelper::EE_SAML_INVALID_URL;
            }

            return $this->response->redirect($errorUrl, true);
            Helpers::__trackError([
                'errorDetails' => isset($resultUserSso) ? $resultUserSso : false,
                'url' => $errorUrl,
            ]);
        } else {
            if (isset($userLogin) && $userLogin) {
                if (!$userLogin->getEmployeeOrUserProfile()) {
                    $return = ['success' => false, 'message' => 'Invalid login credentials'];
                    goto end_of_function;
                }
                $errorUrl = $userLogin->getEmployeeOrUserProfile()->getAppUrl() . SamlHelper::DSP_HR_SAML_INVALID_URL;
                return $this->response->redirect($errorUrl, true);
                Helpers::__trackError([
                    'errorDetails' => isset($resultUserSso) ? $resultUserSso : false,
                    'url' => $errorUrl,
                ]);
            } else {
//                Helpers::__trackError([
//                    'errorDetails' => isset($resultUserSso) ? $resultUserSso : false,
//                    'url' => null,
//                ]);
                $return = ['success' => false, 'message' => 'Invalid login credentials'];
                goto end_of_function;

            }
        }

        end_of_function:

        $di = DI::getDefault();
        $view = new \Phalcon\Mvc\View\Simple();
        $view->setDi($di);
        $view->registerEngines(array(
            ".volt" => function ($view, $di) {
                $volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di);
                $volt->setOptions(
                    array(
                        'compiledPath' => $di->getShared('appConfig')->application->cacheDir,
                        'compiledSeparator' => '_',
                        'compileAlways' => true,
                    )
                );
                $compiler = $volt->getCompiler();
                $volt->getCompiler()->addFunction(
                    'currency_format',
                    function ($keys) {
                        return 'number_format(' . $keys . ', ".", ",")';
                    }
                );
                return $volt;
            }
        ));
        $mainLanguage = isset($company) ? $company->getLanguage() : '';
        // Translate label
        $constants = ConstantExt::getSamlInvalidList($mainLanguage != "" ? $mainLanguage : SupportedLanguageExt::LANG_EN);
        $view->setViewsDir($di->getShared('appConfig')->application->templatesVoltDir);
        $html = $view->render('verify/sso_invalid', [
            'message' => isset($return['message']) ? $return['message'] : null,
            'success' => isset($return['success']) ? $return['success'] : null,
            'constants' => $constants,
            'current_date' => date("d/m/Y"),
        ]);
        $this->response->setHeader('Access-Control-Allow-Origin', $header);
        return $this->response->appendContent($html);
//        die($html);
    }

    /**
     * Just call ajax action
     */
    public function loginCallbackAction()
    {
        $resultUserSso = ['detail' => [], 'success' => false, 'message' => ''];
        $this->checkPost();
        $detect = new \Mobile_Detect();
        $isMob = $detect->isMobile();

        $credential = Helpers::__getRequestValue('credential');
        $password = Helpers::__getRequestValue('password');

        //1 check Classic UserLogin
        $userLogin = UserLoginExt::findFirstByEmail($credential);
        if ($userLogin) {
            //2 check userCognito Exist => his first login
            if ($userLogin->isConvertedToUserCognito() == false) {
                $resultLogin = ApplicationModel::__loginUserOldMethod($credential, $password);
                if ($resultLogin['success'] == false) {
                    $resultUserSso = $resultLogin;
                    goto end_of_function;
                } else {
                    $userLogin = UserLoginExt::findFirstByEmail($credential);
                    $tokenUserDataToken = ApplicationModel::__getUserTokenOldMethod($userLogin);
                    $result = [
                        'success' => false,
                        'token' => $tokenUserDataToken,
                        'message' => 'UserNotFoundException',
                    ];
                    goto end_of_function;
                }
            } else {
                goto aws_cognito_login;
            }
        }

        //2 check cognitoLogin
        aws_cognito_login:
        $cognitoResultLogin = ApplicationModel::__loginUserCognitoByEmail($credential, $password);

        if ($cognitoResultLogin['success'] == true) {
            $resultUserSso = ['success' => true, 'token' => $cognitoResultLogin['accessToken'], 'refreshToken' => $cognitoResultLogin['refreshToken']];
            goto check_sso;
        } else {
            //password correct and user not confirmed
            if (isset($cognitoResultLogin['exceptionType']) && $cognitoResultLogin['exceptionType'] == 'PasswordResetRequiredException') {
                $resultUserSso = [
                    'success' => false,
                    '$cognitoResultLogin' => $cognitoResultLogin,
                    'detail' => isset($cognitoResultLogin['message']) ? $cognitoResultLogin['message'] : (isset($cognitoResultLogin['detail']) ? $cognitoResultLogin['detail'] : ''),
                    'errorType' => $cognitoResultLogin['exceptionType'],
                    'message' => 'LOGIN_FAILED_TEXT'
                ];
                goto end_of_function;
            } else if (isset($cognitoResultLogin['UserNotFoundException']) && $cognitoResultLogin['UserNotFoundException'] == true) {
                $resultUserSso = [
                    'success' => false,
                    '$cognitoResultLogin' => $cognitoResultLogin,
                    'detail' => isset($cognitoResultLogin['message']) ? $cognitoResultLogin['message'] : (isset($cognitoResultLogin['detail']) ? $cognitoResultLogin['detail'] : ''),
                    'message' => $cognitoResultLogin['exceptionType']
                ];
                goto end_of_function;
            } else if (isset($cognitoResultLogin['UserNotConfirmedException']) && $cognitoResultLogin['UserNotConfirmedException'] == true) {
                $userLogin = UserLoginExt::findFirstByEmail($credential);
                $tokenUserDataToken = ApplicationModel::__getUserTokenOldMethod($userLogin);
                $resultUserSso = [
                    'success' => false,
                    'token' => $tokenUserDataToken,
                    'message' => $cognitoResultLogin['exceptionType']
                ];
            } else if (isset($cognitoResultLogin['NewPasswordRequiredException']) && $cognitoResultLogin['NewPasswordRequiredException'] == true) {
                $userLogin = UserLoginExt::findFirstByEmail($credential);
                $tokenUserDataToken = ApplicationModel::__getUserTokenOldMethod($userLogin);
                $resultUserSso = [
                    'success' => false,
                    'token' => $tokenUserDataToken,
                    'session' => $cognitoResultLogin['session'],
                    'challengeName' => $cognitoResultLogin['name'],
                    'message' => "NewPasswordRequiredException"
                ];
                goto end_of_function;
            } else {
                $resultUserSso = [
                    'success' => false,
                    '$cognitoResultLogin' => $cognitoResultLogin,
                    'detail' => isset($cognitoResultLogin['message']) ? $cognitoResultLogin['message'] : (isset($cognitoResultLogin['detail']) ? $cognitoResultLogin['detail'] : ''),
                    'errorType' => $cognitoResultLogin['exceptionType'],
                    'message' => $cognitoResultLogin['exceptionType']
                ];
            }
        }

        check_sso:

        if ($resultUserSso['success'] == true) {
            $userLoginSsoValid = $userLogin->getValidUserLoginSso($this->request->getClientAddress());
            if (!$userLoginSsoValid) {
                $uuid = Helpers::__uuid();
                $newUserLoginSso = new UserLoginSsoExt();
                $newUserLoginSso->setUuid($uuid);
                $newUserLoginSso->setUserLoginId($userLogin->getId());
                $newUserLoginSso->setAccessToken($cognitoResultLogin['accessToken']);
                $newUserLoginSso->setRefreshToken($cognitoResultLogin['refreshToken']);
                //$newUserLoginSso->setSamlToken("");
                $newUserLoginSso->setLifetime(time() + CacheHelper::__TIME_10_MINUTES);
                $newUserLoginSso->setIpAddress($this->request->getClientAddress());
                $newUserLoginSso->setIsAlive(UserLoginSsoExt::ALIVE_YES);

                $resultUserSso = $newUserLoginSso->__quickCreate();
                if ($resultUserSso['success'] == false) {
                    $resultUserSso['errorType'] = 'canNotCreateLoginSSO';
                    goto end_of_function;
                }
            } else {
                $uuid = $userLoginSsoValid->getUuid();
                $userLoginSsoValid->setUuid($uuid);
                $userLoginSsoValid->setUserLoginId($userLogin->getId());
                $userLoginSsoValid->setAccessToken($cognitoResultLogin['accessToken']);
                $userLoginSsoValid->setRefreshToken($cognitoResultLogin['refreshToken']);
                //$newUserLoginSso->setSamlToken("");
                $userLoginSsoValid->setLifetime(time() + CacheHelper::__TIME_10_MINUTES);
                $userLoginSsoValid->setIpAddress($this->request->getClientAddress());
                $userLoginSsoValid->setIsAlive(UserLoginSsoExt::ALIVE_YES);
                $resultUserSso = $userLoginSsoValid->__quickUpdate();
                if ($resultUserSso['success'] == false) {
                    $resultUserSso['errorType'] = 'canNotUpdateLoginSSO';
                    goto end_of_function;
                }
            }

            if ($userLogin->isEmployee()) {
                if ($isMob) {
                    $redirectUrl = $userLogin->getEmployeeOrUserProfile()->getMobileAppUrl() . SamlHelper::EE_MOBILE_SAML_POSTFIX_URL . '/' . $uuid;
                } else {
                    $redirectUrl = $userLogin->getEmployeeOrUserProfile()->getAppUrl() . SamlHelper::EE_SAML_POSTFIX_URL . '/' . $uuid;
                }
            } else {
                $redirectUrl = $userLogin->getEmployeeOrUserProfile()->getAppUrl() . SamlHelper::DSP_HR_SAML_POSTFIX_URL . '/' . $uuid;
            }
            return $this->response->redirect($redirectUrl, true);
            exit;
        }
        end_of_function:
        $resultUserSso['email'] = $credential;
        $resultUserSso['password'] = $password;
        $this->response->setJsonContent($resultUserSso);
        return $this->response->send();
    }

    /**
     *
     */
    public function loginErrorAction()
    {
        $this->view->enable();
        $this->view->setLayout("error");
        $this->view->pick('verify/sso_invalid');
        $this->response->setStatusCode(404, "Not Found Content");
        $this->response->send();
    }

}
