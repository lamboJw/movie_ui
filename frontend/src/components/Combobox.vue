<template>
  <div class="combobox" ref="wrapper">
    <div class="combobox-input-wrap">
      <input
        ref="inputEl"
        :value="displayText"
        @input="onInput"
        @focus="open"
        @keydown="onKeydown"
        type="text"
        :placeholder="placeholder"
        class="combobox-input"
      >
      <button v-if="modelValue" class="clear-btn" @click="onClear" tabindex="-1">&times;</button>
    </div>
    <div v-if="isOpen && filteredOptions.length" class="combobox-dropdown">
      <div
        v-for="(opt, i) in filteredOptions"
        :key="opt"
        class="combobox-option"
        :class="{ highlighted: i === highlightIndex }"
        @mousedown.prevent="select(opt)"
        @mouseenter="highlightIndex = i"
      >{{ opt }}</div>
    </div>
    <div v-if="isOpen && !filteredOptions.length && inputText" class="combobox-dropdown">
      <div class="combobox-empty">无匹配</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: '' },
  modelValue: { type: String, default: '' }
})

const emit = defineEmits(['update:modelValue', 'change'])

const wrapper = ref(null)
const inputEl = ref(null)
const isOpen = ref(false)
const inputText = ref('')
const highlightIndex = ref(0)

const displayText = computed(() => {
  return isOpen.value ? inputText.value : props.modelValue
})

const filteredOptions = computed(() => {
  if (!inputText.value) return props.options
  const q = inputText.value.toLowerCase()
  return props.options.filter(o => o.toLowerCase().includes(q))
})

function open() {
  isOpen.value = true
  inputText.value = props.modelValue
  highlightIndex.value = 0
}

function close() {
  isOpen.value = false
  inputText.value = ''
}

function onInput(e) {
  inputText.value = e.target.value
  if (!isOpen.value) isOpen.value = true
  highlightIndex.value = 0
}

function select(opt) {
  emit('update:modelValue', opt)
  emit('change')
  close()
}

function onClear() {
  emit('update:modelValue', '')
  emit('change')
  inputEl.value?.focus()
}

function onKeydown(e) {
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    highlightIndex.value = Math.min(highlightIndex.value + 1, filteredOptions.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    highlightIndex.value = Math.max(highlightIndex.value - 1, 0)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (filteredOptions.value[highlightIndex.value]) {
      select(filteredOptions.value[highlightIndex.value])
    }
  } else if (e.key === 'Escape') {
    close()
    inputEl.value?.blur()
  }
}

function onClickOutside(e) {
  if (wrapper.value && !wrapper.value.contains(e.target)) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('mousedown', onClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('mousedown', onClickOutside)
})
</script>

<style scoped>
.combobox {
  position: relative;
  width: 130px;
}

.combobox-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.combobox-input {
  width: 100%;
  padding: 8px 28px 8px 12px;
  background: #16213e;
  border: 1px solid #333;
  border-radius: 6px;
  color: white;
  font-size: 13px;
  box-sizing: border-box;
}

.combobox-input:focus {
  outline: none;
  border-color: #e94560;
}

.combobox-input::placeholder {
  color: #666;
}

.clear-btn {
  position: absolute;
  right: 4px;
  background: none;
  border: none;
  color: #888;
  font-size: 18px;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
}

.clear-btn:hover {
  color: #fff;
}

.combobox-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  max-height: 220px;
  overflow-y: auto;
  background: #1a1a2e;
  border: 1px solid #444;
  border-radius: 6px;
  z-index: 100;
}

.combobox-option {
  padding: 8px 12px;
  font-size: 13px;
  color: #ccc;
  cursor: pointer;
}

.combobox-option:hover,
.combobox-option.highlighted {
  background: #2a2a4e;
  color: #fff;
}

.combobox-empty {
  padding: 12px;
  text-align: center;
  color: #666;
  font-size: 13px;
}

@media (max-width: 768px) {
  .combobox {
    width: 100%;
  }

  .combobox-input {
    font-size: 12px;
    padding: 6px 28px 6px 10px;
  }
}
</style>
