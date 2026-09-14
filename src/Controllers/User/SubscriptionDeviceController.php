<?php

namespace App\Controllers\User;

use App\Controllers\LinkController;
use App\Controllers\UserController;
use App\Models\SubscriptionDevice;
use App\Utils\Tools;
use Psr\Http\Message\ResponseInterface;

class SubscriptionDeviceController extends UserController
{
    private const MAX_ACTIVE_DEVICES = 20;

    public function index($request, $response, $args): ResponseInterface
    {
        $devices = SubscriptionDevice::where('user_id', $this->user->id)
            ->orderBy('id', 'desc')
            ->get();

        return $response->write(
            $this->view()
                ->assign('subscription_devices', $devices)
                ->assign('subscription_profiles', self::profiles())
                ->display('user/subscription_devices.tpl')
        );
    }

    public function create($request, $response, $args): ResponseInterface
    {
        $name = trim((string) $request->getParam('name'));
        $profile = strtolower(trim((string) $request->getParam('profile')));
        $expiresDays = max(0, min(3650, (int) $request->getParam('expires_days')));

        if ($name === '' || mb_strlen($name) > 64) {
            return $response->withJson(['ret' => 0, 'msg' => '设备名称不能为空且不能超过64个字符'], 400);
        }
        if (!array_key_exists($profile, self::profiles())) {
            return $response->withJson(['ret' => 0, 'msg' => '不支持的订阅格式'], 400);
        }
        $activeCount = SubscriptionDevice::where('user_id', $this->user->id)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', time());
            })
            ->count();
        if ($activeCount >= self::MAX_ACTIVE_DEVICES) {
            return $response->withJson(['ret' => 0, 'msg' => '最多创建20个有效设备订阅'], 409);
        }

        [$device, $token] = $this->newDevice($name, $profile, $expiresDays);
        return $response->withJson([
            'ret' => 1,
            'msg' => '设备订阅已创建，请立即复制；令牌不会再次显示',
            'url' => self::deviceUrl($token, $profile),
            'id' => (int) $device->id,
        ], 201);
    }

    public function rotate($request, $response, $args): ResponseInterface
    {
        $device = $this->findDevice((int) $args['id']);
        if ($device === null) {
            return $response->withJson(['ret' => 0, 'msg' => '设备不存在'], 404);
        }
        $token = Tools::genRandomChar(48);
        $device->token_hash = hash('sha256', $token);
        $device->token_prefix = substr($token, 0, 10);
        $device->revoked_at = null;
        $device->last_access_at = null;
        $device->last_access_ip = null;
        $device->save();

        return $response->withJson([
            'ret' => 1,
            'msg' => '令牌已轮换，旧地址立即失效',
            'url' => self::deviceUrl($token, (string) $device->profile),
        ]);
    }

    public function revoke($request, $response, $args): ResponseInterface
    {
        $device = $this->findDevice((int) $args['id']);
        if ($device === null) {
            return $response->withJson(['ret' => 0, 'msg' => '设备不存在'], 404);
        }
        $device->revoked_at = time();
        $device->save();
        return $response->withJson(['ret' => 1, 'msg' => '设备订阅已撤销']);
    }

    private function newDevice(string $name, string $profile, int $expiresDays): array
    {
        do {
            $token = Tools::genRandomChar(48);
            $tokenHash = hash('sha256', $token);
        } while (SubscriptionDevice::where('token_hash', $tokenHash)->exists());

        $device = new SubscriptionDevice();
        $device->user_id = $this->user->id;
        $device->name = $name;
        $device->profile = $profile;
        $device->token_hash = $tokenHash;
        $device->token_prefix = substr($token, 0, 10);
        $device->created_at = time();
        $device->expires_at = $expiresDays > 0 ? time() + $expiresDays * 86400 : null;
        $device->save();
        return [$device, $token];
    }

    private function findDevice(int $id): ?SubscriptionDevice
    {
        return SubscriptionDevice::where('id', $id)
            ->where('user_id', $this->user->id)
            ->first();
    }

    private static function deviceUrl(string $token, string $profile): string
    {
        return rtrim((string) $_ENV['subUrl'], '/') . '/' . $token . '/' . $profile;
    }

    public static function profiles(): array
    {
        return [
            'auto' => '自动识别',
            'mihomo' => 'Mihomo / Clash Meta',
            'fancyss' => '梅林 FancySS',
            'sing-box' => 'sing-box',
            'v2rayn' => 'V2RayN / V2RayNG',
            'stash' => 'Stash',
            'surge' => 'Surge 4',
            'quantumultx' => 'Quantumult X',
            'shadowrocket' => 'Shadowrocket',
        ];
    }
}
