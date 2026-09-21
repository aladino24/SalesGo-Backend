<?php

namespace App\Services;

class DeepLinkService
{
    public function approval(int $id): string
    {
        return '/approval?approvalId='.$id;
    }

    public function journey(int $id): string
    {
        return '/journey?journeyId='.$id;
    }

    public function deliveryNote(int $id): string
    {
        return '/journey?deliveryNoteId='.$id;
    }

    public function outlet(int $id): string
    {
        return '/outlet?outletId='.$id;
    }

    public function visit(int $id, ?int $outletId = null): string
    {
        return '/visit?visitId='.$id.($outletId ? '&outletId='.$outletId : '');
    }

    public function notification(int $id): string
    {
        return '/notifications?notificationId='.$id;
    }
}
