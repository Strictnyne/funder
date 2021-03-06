<?php

namespace App\Http\Controllers;

use Eos\Common\Exceptions\EosAuthException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Eos\Common\WalletService;
use App\Player;
use App\Exceptions\FundingException;
use Eos\Common\InteractiveCoreService;
use Illuminate\Support\Facades\Log;


//
class FundingController extends Controller
{

    /**
     * @SWG\Post(
     *   path="/api/funding/login",
     *   summary="Log in a player",
     *   operationId="logInPlayer",
     *   tags={"funding"},
     * @SWG\Parameter(
     *     in="body",
     *     name="credentials",
     *     required=true,
     *     @SWG\Schema(ref="#/definitions/LoginCredentials")
     *   ),
     * @SWG\Response(response=200, description="successful",
     *     @SWG\Schema(
     *       type="array",
     *       @SWG\Items(ref="#/definitions/Player"))
     *   ),
     *   @SWG\Response(response=550, description="Failed login exception")
     *  )
     **/
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'registrar_id' => 'required'
        ]);

        $icore = new InteractiveCoreService();
        $auth = $icore->loginPlayer( $request->input('email'), $request->input('password') );

        if( $auth['code'] != 200)
        { throw new FundingException('_AUTHERROR',['message' => $auth['message']] ); }

        $playerdata = $icore->getPlayerInformation( $request->input('registrar_id') );

        if( !$playerdata || $playerdata->Email != $request->input('email') )
        { throw new FundingException( '_AUTHERROR',['message' => 'missing player info']); }

        $construct_player = [
            'registrar_id' => strval($playerdata->PlayerCardId),
            'activateddatetime' => $playerdata->ActivatedDateTime,
            'lastlogindatetime' => $playerdata->LastLoginDateTime,
            'cashbalancepence' => intval($playerdata->CashBalancePence),
            'username' => $playerdata->UserName,
            'firstname' => $playerdata->FirstName,
            'lastname' => $playerdata->LastName,
            'phone' => $playerdata->Phone,
            'email' => $playerdata->Email,
            'address1' => $playerdata->Address1,
            'address2' => $playerdata->Address2,
            'city' => $playerdata->City,
            'state' => $playerdata->State,
            'zip' => $playerdata->PostalCode,
            'playerstate' => $playerdata->PlayerState
        ];

        $match_player = new Player($construct_player);
        $hash = $match_player->makeHash();
        $player = Player::byHash($hash)->first();
        if( !$player )
        {
            $match_player->save();
            $player = $match_player;
        }

        $ws = new WalletService();
        $accounts = $ws->getAccounts($player->toSimplePlayer());
        $funding = $ws->getFundingOptions($player);

        return response()->json([
            'player' => $player,
            'accounts' => $accounts,
            'funding' => $funding
        ]);
    }

    /**
     * @param Request $request
     * @throws FundingException
     * @throws \Eos\Common\Exceptions\EosException
     * @throws \Eos\Common\Exceptions\EosServiceException
     * @throws \Eos\Common\Exceptions\EosWalletServiceException
     */
    public function addPaymentMethod(Request $request) {
        $info = json_decode($request->getContent(), true);

        $type = $info['funding_method_type'];
        $nickname = $info['payment_method_nickname'];


        if($type === "card_profile") {
            $token = $info['provider_temporary_token'];

            $details = [
                'provider_temporary_token' => $token,
            ];
        }

        $details['address'] = [
            //todo: may need a better nickname mechanism for addresses
            'address_nickname' => str_slug($info['billing_details']['address1']),
            'address1' => $info['billing_details']['address1'],
            'address2' => $info['billing_details']['address2'],
            'city' => $info['billing_details']['city'],
            'state' => $info['billing_details']['state'],
            'country' => $info['billing_details']['country'],
            'zip' => $info['billing_details']['zip'],
        ];

        if($type === "eft_profile") {
            $details['eft_profile'] = [
                'bank_account_type' => $info['eft_profile']['bank_account_type'],
                'account_holder_name' => $info['eft_profile']['account_holder_name'],
                'account_number' => $info['eft_profile']['account_number'],
                'routing_number' => $info['eft_profile']['routing_number'],
                'bank_name' => $info['eft_profile']['bank_name'],
            ];
        }

        $default = $info['default'];
        $hash = $info['playerHash'];
        $player = Player::byHash($hash)->first();

        if(!$player) {
            throw new FundingException( '_AUTHERROR',['message' => 'missing player info in addpaymentmethod']);
        }

        $ws = new WalletService();
        return response()->json(
            $ws->addPaymentMethod($type, $nickname, $details, $default, $player->toSimplePlayer())
        );

    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Eos\Common\Exceptions\EosException
     * @throws \Eos\Common\Exceptions\EosServiceException
     * @throws \Eos\Common\Exceptions\EosWalletServiceException
     */
    public function fundWallet(Request $request) {
        $info = json_decode($request->getContent(), true);

        $ws = new WalletService();

        $amount = $info['amount'];
        $hash = $info['playerHash'];
        $player = Player::byHash($hash)->first();
        $type = $info['funding_method_type'];

        if(isset($info['existingMethod']) && $info['existingMethod'] === true) {
//            dd($info);
            $profile_id = ['method_id'];

            return response()->json(
                $ws->fundWalletAccount($type, null ,null , $profile_id, $amount, $player->toSimplePlayer())
            );
        }

        $token = $info['provider_temporary_token'];
        $profile_id = null;

        $address = [
            'address_nickname' => $info['billing_details']['address_nickname'],
            'address1' => $info['billing_details']['address1'],
            'address2' => $info['billing_details']['address2'],
            'city' => $info['billing_details']['city'],
            'state' => $info['billing_details']['state'],
            'country' => $info['billing_details']['country'],
            'zip' => $info['billing_details']['zip'],
        ];

//        Log::info("About to fund with type ".$type.", token ".$token.", zip ".$address['zip'].", amount $". $amount / 100.0);

        if($info['save_method'] === true) {
            $nickname = $info['payment_method_nickname'];
            $default = $info['default'];

            $details = [
                'provider_temporary_token' => $token,
            ];

            $details['address'] = [
                'provider_temporary_token' => $token,
                'address_nickname' => str_slug($info['billing_details']['address1']),
                'address1' => $info['billing_details']['address1'],
                'address2' => $info['billing_details']['address2'],
                'city' => $info['billing_details']['city'],
                'state' => $info['billing_details']['state'],
                'country' => $info['billing_details']['country'],
                'zip' => $info['billing_details']['zip'],
            ];

            $ws->addPaymentMethod($type, $nickname, $details, $default, $player->toSimplePlayer());
        }

        return response()->json(
            $ws->fundWalletAccount($type, $token, $address, $profile_id, $amount, $player->toSimplePlayer())
        );
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Eos\Common\Exceptions\EosException
     * @throws \Eos\Common\Exceptions\EosServiceException
     */
    public function getPaymentMethods(Request $request) {
        $info = json_decode($request->getContent(), true);
        $hash = $info['playerHash'];
        $player = Player::byHash($hash)->first();

        $ws = new WalletService();
        $options = $ws->getFundingOptions($player);

        return response()->json(
            $options
        );
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Eos\Common\Exceptions\EosException
     * @throws \Eos\Common\Exceptions\EosServiceException
     */
    public function getAccountHistory(Request $request) {
        $info = json_decode($request->getContent(), true);
        $hash = $info['playerHash'];
        $player = Player::byHash($hash)->first();
        $filters = [];
        $paging = ['page' => $info['currentPageQueried'], 'page_size' => $info['itemsPerPage']];

        $ws = new WalletService();
        $history = $ws->getAccountHistory($player->id, $filters, $paging, $player);

        return response()->json(
            $history
        );
    }

    /**
     * @SWG\Get(
     *   path="/api/funding",
     *   summary="Get stored funding methods",
     *   operationId="getFunding",
     *   tags={"funding"},
     * @SWG\Parameter(
     *     in="query",
     *     name="playerhash",
     *     required=true,
     *     type="string",
     *     description="required player hash from login"
     *   ),
     * @SWG\Response(response=200, description="successful",
     *     @SWG\Schema(
     *       type="array",
     *       @SWG\Items(ref="#/definitions/FundingList"))
     *   ),
     *   @SWG\Response(response=550, description="Failed login exception")
     *  )
     **/
    public function getFunding(Request $request)
    {
        $request->validate([
            'playerhash' => 'required'
        ]);

        $player = Player::byHash($request->input('playerhash'))->first();
        if( !$player )
        { throw new AuthenticationException(); }

        $wallet = new WalletService();
        $funding = $wallet->getFundingOptions( $player->toSimplePlayer(), null );

        return response()->json($funding);
    }


    /**
     * @param $hash
     * @return \Illuminate\Http\JsonResponse
     * @throws \Eos\Common\Exceptions\EosException
     * @throws \Eos\Common\Exceptions\EosServiceException
     */
    public function getPlayerBalance($hash) {
        $player = Player::byHash($hash)->first();
        $balance = 0;

        $wallet = new WalletService();
        $accounts = $wallet->getAccounts($player);

        foreach($accounts as $account) {
            $balance += $account->balance;
        }

        return response()->json(['balance' => $balance]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Eos\Common\Exceptions\EosException
     * @throws \Eos\Common\Exceptions\EosServiceException
     */
    public function checkNickname(Request $request) {
        $info = json_decode($request->getContent(), true);

        $hash = $info['playerHash'];
        $alias = $info['nickname'];
        $type = $info['type'];
        $player = Player::byHash($hash)->first();
        $valid = null;
        $field = null;

        if($type === "addresses") {
            $field = "address_nickname";
        }
        else {
            $field = "payment_method_nickname";
        }

        $ws = new WalletService();
        $nicknames = $ws->getFundingOptions($player)->$type;

        foreach($nicknames as $key => $value) {
            if($alias === $value->$field) {
                $valid = false;
                break;
            }
            else {
                $valid = true;
            }
        }

        return response()->json(
            ['valid' => $valid]
        );
    }

    /**
     * Other funding methods here
     */
}

/**
 * @SWG\Definition(required={"email","password","registrar_id"}, type="object", @SWG\Xml(name="LoginCredentials"))
 * @SWG\Property(format="string", property="email", example="me@email.com", description="The player email credential.")
 * @SWG\Property(format="string", property="password", example="xxxyyy", description="The player password credential.")
 * @SWG\Property(format="string", property="registrar_id", example="224", description="The player registrar id (playercardid).")
 **/
class LoginCredentials {}

/**
 * @SWG\Definition(required={}, type="object", @SWG\Xml(name="FundingList"))
 * @SWG\Property(property="eft-profiles", description="All stored EFT profiles for the player.",
 *     type="array",
 *       @SWG\Items(ref="#/definitions/EftProfile"))
 * @SWG\Property(property="card-profiles", description="All stored Card profiles for the player.",
 *     type="array",
 *       @SWG\Items(ref="#/definitions/CardProfile"))
 **/
class FundingList {}

/**
 * @SWG\Definition(required={"provider","name"}, type="object", @SWG\Xml(name="EftProfile"))
 * @SWG\Property(format="int64", property="id", example=21, description="EFT Profile identifier.")
 * @SWG\Property(format="string", property="name", example="Moms Bank Account", description="Player selected name")
 * @SWG\Property(format="string", property="provider", example="PaySafe", description="The e-commerce provider in use")
 * @SWG\Property(format="string", property="provider_funding_token", example="GGX435R-21er42-d345", description="Provider external ID for profile")
 * @SWG\Property(format="string", property="card_type", example="MasterCard", description="Card type identifier")
 * @SWG\Property(format="string", property="state", example="active", description="State of the profile: active, expired, pending")
 * @SWG\Property(format="boolean", property="is_default_funding", example=true, description="True if this is default funding option")
 **/
class EftProfile {}

/**
 * @SWG\Definition(required={"provider","name"}, type="object", @SWG\Xml(name="CardProfile"))
 * @SWG\Property(format="int64", property="id", example=21, description="EFT Profile identifier.")
 * @SWG\Property(format="string", property="name", example="Moms Bank Account", description="Player selected name")
 * @SWG\Property(format="string", property="provider", example="PaySafe", description="The e-commerce provider in use")
 * @SWG\Property(format="string", property="provider_funding_token", example="GGX435R-21er42-d345", description="Provider external ID for profile")
 * @SWG\Property(format="string", property="card_type", example="MasterCard", description="Card type identifier")
 * @SWG\Property(format="string", property="state", example="active", description="State of the profile: active, expired, pending")
 * @SWG\Property(format="boolean", property="is_default_funding", example=true, description="True if this is default funding option")
 **/
class CardProfile {}
