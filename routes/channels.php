<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    return $user->chats()->where('chats.id', $chatId)->exists();
});

// Presence channel kolaborasi form survey — hanya user yang boleh mengedit
// (pemilik / kolaborator approved / super admin) yang join sebagai member.
// Return array = data member presence yang dilihat editor lain.
// Param ID mentah (bukan model binding): client join pakai surveyId numerik
// dan event juga broadcast ke nama channel numerik yang sama — model binding
// akan gagal karena HasEncryptedRouteKey mengharap ciphertext.
Broadcast::channel('survey.{surveyId}', function ($user, $surveyId) {
    $survey = \App\Models\Survey::find((int) $surveyId);

    return $survey && $user->can('update', $survey)
        ? ['id' => $user->id, 'name' => $user->name]
        : null;
});
