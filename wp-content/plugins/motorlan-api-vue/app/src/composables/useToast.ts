import { ref } from 'vue'

const isToastVisible = ref(false)
const toastMessage = ref('')
const toastColor = ref('success')

export const useToast = () => {
  const showToast = (message: string, color: string = 'success') => {
    toastMessage.value = message
    toastColor.value = color
    isToastVisible.value = true
  }

  return {
    isToastVisible,
    toastMessage,
    toastColor,
    showToast,
  }
}
