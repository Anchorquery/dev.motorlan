<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Lista de requisitos de contraseña (mismas 5 reglas que el registro y que
 * motorlan_validate_password_strength en el backend). Se usa en restablecer y en
 * cambiar contraseña, que antes solo comprobaban la longitud.
 */
const props = defineProps<{
  password: string
}>()

const { t } = useI18n()

const checks = computed(() => {
  const p = props.password || ''

  return [
    { ok: p.length >= 8, label: t('register.password_req_length', 'Mínimo 8 caracteres') },
    { ok: /[A-Z]/.test(p), label: t('register.password_req_upper', 'Una letra mayúscula (A-Z)') },
    { ok: /[a-z]/.test(p), label: t('register.password_req_lower', 'Una letra minúscula (a-z)') },
    { ok: /[0-9]/.test(p), label: t('register.password_req_digit', 'Un número (0-9)') },
    { ok: /[^A-Za-z0-9]/.test(p), label: t('register.password_req_special', 'Un carácter especial (!#$%…)') },
  ]
})
</script>

<template>
  <div>
    <div class="text-caption text-medium-emphasis font-weight-medium mb-2">
      {{ t('register.password_requirements_title', 'La contraseña debe tener:') }}
    </div>
    <div class="d-flex flex-column gap-1">
      <div
        v-for="check in checks"
        :key="check.label"
        class="d-flex align-center gap-2 text-caption"
      >
        <VIcon
          :icon="check.ok ? 'tabler-circle-check-filled' : 'tabler-circle'"
          :color="check.ok ? 'success' : 'medium-emphasis'"
          size="16"
        />
        <span :class="check.ok ? 'text-success' : 'text-medium-emphasis'">
          {{ check.label }}
        </span>
      </div>
    </div>
    <div class="text-caption text-medium-emphasis mt-2">
      {{ t('password_rules.no_personal_data', 'No uses tu nombre ni tu email en la contraseña.') }}
    </div>
  </div>
</template>
