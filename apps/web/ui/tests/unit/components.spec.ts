import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import CodeBlock from '../../src/components/CodeBlock.vue'
import OsTabs from '../../src/components/OsTabs.vue'
import { clipboardWrite } from '../helpers'

describe('CodeBlock', () => {
  it('affiche le code et le copie', async () => {
    const wrapper = mount(CodeBlock, { props: { code: 'gabarit new mon-app', title: 'Terminal' } })
    expect(wrapper.get('pre').text()).toBe('gabarit new mon-app')
    expect(wrapper.text()).toContain('Terminal')

    await wrapper.get('button.copy').trigger('click')
    await flushPromises()

    expect(clipboardWrite).toHaveBeenCalledWith('gabarit new mon-app')
    expect(wrapper.get('button.copy').text()).toBe('Copié')
  })
})

describe('OsTabs', () => {
  const tabs = [
    { label: 'Linux / macOS', code: 'curl … | sh' },
    { label: 'Windows (PowerShell)', code: 'irm … | iex' },
  ]

  it("passe d'un système à l'autre", async () => {
    const wrapper = mount(OsTabs, { props: { tabs } })
    const buttons = wrapper.findAll('[role="tab"]')
    expect(buttons).toHaveLength(2)

    await buttons[1]!.trigger('click')
    expect(wrapper.get('pre').text()).toBe('irm … | iex')
    expect(buttons[1]!.attributes('aria-selected')).toBe('true')

    await buttons[0]!.trigger('click')
    expect(wrapper.get('pre').text()).toBe('curl … | sh')
  })
})
