<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;

use app\models\Users;

use yii\authclient\clients\Twitter;
use yii\authclient\OAuthToken;

class TwitterController extends Controller
{
    public function connection()
    {
        // Створюємо OAuthToken
        $token = new OAuthToken([
            'token' => Yii::$app->params['twitterAccessToken'],
            'tokenSecret' => Yii::$app->params['twitterAccessTokenSecret']
        ]);
        
        // Запускаємо Twitter, використовуючи отриманий token
        $twitter = new Twitter([
            'accessToken' => $token,
            'consumerKey' => Yii::$app->params['twitterApiKey'],
            'consumerSecret' => Yii::$app->params['twitterApiSecret']
        ]);

        return $twitter;
    }

    /* Виведення стрічки найновіших твітів для обраної групи користувачів */
    public function actionFeed()
    {
        $request = Yii::$app->request;

        $auth_id = $request->get('id');
        $secret = $request->get('secret');

        // Перевіряємо наявність необхідних вхідних параметрів, та при необхідності виводимо error-повідомлення
        if ($auth_id == '' || $secret == '' || strlen($auth_id) != 32) {
            $errmsg = json_encode(['error' => 'missing parameter']);
            return $errmsg;
        }

        $secret_check = sha1($auth_id); // Знаходимо перевірочний secret key

        if ($secret === $secret_check) { // Проводимо перевірку відповідності secret key
            $twitter = $this->connection();
            $results = [];

            $users = Users::find()->all();        

            foreach ($users as $user) {
              $query_string = 'statuses/user_timeline.json?screen_name='.$user->username.'&count=1'; // Виводимо найновіший tweet для кожного аккаута
              $result = $twitter->api($query_string, 'GET'); // Робимо запит, вказаний у документації і отриманими результатами заповнюємо масив     
              foreach ($result as $item) {
                $tmp = [];
                $tmp['user'] = $item['user']['screen_name'];
                $tmp['tweet'] = $item['text'];
                $tmp['hashtags'] = [];
                foreach ($item['entities']['hashtags'] as $hashtag) {
                  $tmp['hashtags'][] = $hashtag['text'];
                }
                $results['feed'][] = $tmp;
              }          
            }

            if ($results == []) { // Виводимо повідомлення про помилку, якщо результуючий масив порожній
                $errmsg = json_encode(['error' => 'internal error']);
                return $errmsg;
            }

            echo '<pre>';
            print_r(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); // Отримуємо json-рядок із кінцевими результатами
            echo '</pre>';
            
        } else { // Виводимо повідомлення про помилку, якщо секретний ключ не співпадає
            $errmsg = json_encode(['error' => 'access denied']);
            return $errmsg;
        }        
    }

    /* Додавання користувача у список для виведення */
    public function actionAdd()
    {
        $request = Yii::$app->request;

        $auth_id = $request->get('id');
        $user = $request->get('user');
        $secret = $request->get('secret');

        // Перевіряємо наявність необхідних вхідних параметрів, та при необхідності виводимо error-повідомлення
        if ($auth_id == '' || $user == '' || $secret == '' || strlen($auth_id) != 32) {
            $errmsg = json_encode(['error' => 'missing parameter']);
            return $errmsg;
        }

        $secret_check = sha1($auth_id . $user); // Знаходимо перевірочний secret key

        if ($secret === $secret_check) { // Проводимо перевірку відповідності secret key           
            $add_user = new Users();
            $add_user->username = $user;

            if (!$add_user->save()) { // Додаємо необхідний запис у список. При помилці операції виводимо повідомлення
                $errmsg = json_encode(['error' => 'internal error']);
                return $errmsg;
            }

        } else { // Виводимо повідомлення про помилку, якщо секретний ключ не співпадає
          $errmsg = json_encode(['error' => 'access denied']);
          return $errmsg;
        }  
    }

    /* Видалення користувача зі списку для виведення */
    public function actionRemove()
    {
        $request = Yii::$app->request;

        $auth_id = $request->get('id');
        $user = $request->get('user');
        $secret = $request->get('secret');

        // Перевіряємо наявність необхідних вхідних параметрів, та при необхідності виводимо error-повідомлення
        if ($auth_id == '' || $user == '' || $secret == '' || strlen($auth_id) != 32) {
            $errmsg = json_encode(['error' => 'missing parameter']);
            return $errmsg;
        }

        $secret_check = sha1($auth_id . $user); // Знаходимо перевірочний secret key

        if ($secret === $secret_check) { // Проводимо перевірку відповідності secret key           
            $user_to_delete = Users::find()->where(['username' => $user])->one(); 
            
            if (!$user_to_delete->delete()) {
              $errmsg = json_encode(['error' => 'internal error']); // Видаляємо необхідний запис зі списку. При помилці операції виводимо повідомлення
              return $errmsg;
            }

        } else { // Виводимо повідомлення про помилку, якщо секретний ключ не співпадає
          $errmsg = json_encode(['error' => 'access denied']);
          return $errmsg;
        }  
    }
}
