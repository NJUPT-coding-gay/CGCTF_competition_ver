<?php

namespace App\Http\Controllers;

use App\challenge;
use App\challenge_user;
use App\User;
use App\team;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ChallengeController extends Controller
{
    //
    public function index()
    {
        if (User::isadmin()) {
            return view('create');
        } else return redirect()->route('login');
    }

    public function newchallenge(Request $data)
    {
        if (User::isadmin()) {
            $data->flash();
            $request = $data->all();
            $add = new challenge;
            $r=$add->create(['title' => $request['title'],
                'class' => $request['class'],
                'description' => $request['description'],
                'url' => $request['url'],
                'flag' => $request['flag'],
                'info'=>$request['info'],
                'score' => $request['score'],
            ]);
            if($r) return redirect()->route('home');
            else return view('create', ['status' => '出现错误']);
        }
        else return redirect()->route('login');
    }

    public function edit($id)
    {
        if (User::isadmin()) {
            $challenge = challenge::find($id);
            return view('edit', ['challenge' => $challenge]);
        } else return redirect()->route('login');
    }

    public function editchallenge($id, Request $data)
    {
        if (User::isadmin()) {
            $data->flash();
            $request = $data->all();
            $challenge = challenge::find($id);
            $challenge->title = $request['title'];
            $challenge->class = $request['class'];
            $challenge->description = $request['description'];
            $challenge->url = $request['url'];
            $challenge->flag = $request['flag'];
            $challenge->info=$request['info'];
            $challenge->score = $request['score'];
            if ($challenge->save()) return redirect()->route('login');
            else return view('edit', ['status' => '出现错误']);
        }
        else return redirect()->route('login');
    }

    public function delete($id)
    {
        if (User::isadmin()) {
            $challenge = challenge::find($id);
            $challenge->users()->detach();
            if ($challenge->delete()) {
                return redirect()->back()->withInput()->withErrors('deleted!');
            } else return redirect()->back()->withInput()->withErrors('unknown error!');
        } else return redirect()->back()->withInput()->withErrors('error!');
    }

    public function showChallenges()
    {
        return view('challenge');
    }

    public function ShowScoreBoard(Request $request)
    {
        // $users = User::scoreboard()->toArray();
        // $perPage = 50;
        // if ($request->has('page')) {
        //     $current_page = $request->input('page');
        //     $current_page = $current_page <= 0 ? 1 : $current_page;
        // } else {
        //     $current_page = 1;
        // }
        // $item = array_slice($users, ($current_page - 1) * $perPage, $perPage); //注释1
        // $total = count($users);
        // $paginator = new LengthAwarePaginator($item, $total, $perPage, $current_page, [
        //     'path' => Paginator::resolveCurrentPath(), //注释2
        //     'pageName' => 'page',
        // ]);
        // $userlist = $paginator->toArray()['data'];
        // return view('scoreboard', ['users' => $userlist, 'paginator' => $paginator]);
        //return $request->get('class');
        $type=$request->get('class')?:'fresh';
        $users = team::scoreboard($type)->toArray();
        // $perPage = 50;
        // if ($request->has('page')) {
        //     $current_page = $request->input('page');
        //     $current_page = $current_page <= 0 ? 1 : $current_page;
        // } else {
        //     $current_page = 1;
        // }
        // $item = array_slice($users, ($current_page - 1) * $perPage, $perPage); //注释1
        // $total = count($users);
        // $paginator = new LengthAwarePaginator($item, $total, $perPage, $current_page, [
        //     'path' => Paginator::resolveCurrentPath(), //注释2
        //     'pageName' => 'page',
        // ]);
        // $userlist = $paginator->toArray()['data'];
        $userlist=$users;
        if($request->get('class')) return $userlist;
        return view('scoreboard', ['users' => $userlist, /*'paginator' => $paginator*/]);
        
    }

    public function ShowScore()
    {
        if (Auth::check()) {
            $id = Auth::id();
            $name=Auth::user()->name;
            $score = User::userscore($id);
            $challenges = User::solvedchallenges($id);
            //return $challenges;
            return view('score', ['name'=>$name , 'score' => $score, 'challenges' => $challenges]);
        } else return redirect()->route('login');
    }

    public function userDetail($id)
    {
            $score = User::userscore($id);
            $challenges = User::solvedchallenges($id);
            $name=User::find($id)->name;
            //return $challenges;
            return view('score', ['name'=>$name ,'score' => $score, 'challenges' => $challenges]);
    }

    //  api part

    /**
     * api method
     *
     * get questions belongs to a class
     *
     * @param Request $request
     * @return mixed
     */
    public function getQuestionsBelongsToClass(Request $request)
    {   
        $user = Auth::guard('api')->user();
        $power=$user?$user->power:'no';
        if(!Hash::check('admin', $power)){
        $challenges = challenge::where('class', $request->get('class'))->where('info','!=','hide')
            ->select('id', 'title', 'score')
            ->get();
        }
        else{
             $challenges = challenge::where('class', $request->get('class'))
            ->select('id', 'title', 'score')
            ->get();
        }
        foreach ($challenges as $challenge => $v) {
             $challenges[$challenge]->solversCount = $challenges[$challenge]->solvedteams()->count();
        }

       // $user = Auth::guard('api')->user();
        if (!!$user) {
            $challenges->map(function ($challenge) use ($user){
                $challenge->passed = $user->challengePassed($challenge->id);
                return $challenge;
            });
        }

        return $challenges;
    }


    /**
     * api method
     *
     * get question detail
     *
     * @param challenge $challenge
     * @return array
     */
    public function getQuestionDetail(challenge $challenge)
    {
        $user = Auth::guard('api')->user();
        $power = !!$user ? Hash::check('admin', $user->power) : false;
        if($challenge->info != 'hide'||$power){
        return [
            'description' => $challenge->description,
            'url' => $challenge->url,
            'class' => $challenge->class,
            'power' => $power
        ];
        }
    }

    /**
     * api method
     *
     * validate flag
     *
     * @param challenge $challenge
     * @param Request $request
     * @return string
     */
    public function submitFlag(challenge $challenge, Request $request)
    {
        $user = Auth::guard('api')->user();

        if ($user->challengePassed($challenge->id)) {
            return 'Already passed';
        }
        if($challenge->info != 'start') return 'Game Over!';
        if (($challenge->flag === $request->get('flag'))&&$challenge->info==='start') {
            $id=$challenge->id;
            $c=challenge::find($id);
            $team=$user->team;
            if($team) {
            $teamates=$team->members;
            foreach ($teamates as $user ) {
               challenge_user::create(['userid' => $user->id, 'challengeid' => $challenge->id]);
            } 
            $team->updated_at=Carbon::now();
            $team->save();
            }
            else{
                challenge_user::create(['userid' => $user->id, 'challengeid' => $challenge->id]);
            }
            $cnt=$c->solvedteams()->count();
            if($cnt){
            $c->score=10000/($cnt+9);
            $c->save();
            }
            return 'true';
        }
        return 'false';
    }

    /**
     * api method
     *
     * delete challenge
     *
     * @param challenge $challenge
     * @return string
     */
    public function deleteChallenge(challenge $challenge)
    {
        $user = Auth::guard('api')->user();

        if (self::isAdmin($user->power)) {
            // 解除对应关系
            $challenge->users()->detach();
            // 删除
            return $challenge->delete() ? 'true' : 'false';
        } else {
            // 需要管理员权限
            return 'Administrator permission is required';
        }
    }


    /**
     * 判断管理员权限
     *
     * @param $power
     * @return mixed
     */
    public static function isAdmin($power)
    {
        return Hash::check('admin', $power);
    }


    public function getSolvers(challenge $challenge)
    {
        $teams=$challenge->solvedteams();

       // $sorted = $teams->sortBy('updated_at');
        return $teams;
    }




}