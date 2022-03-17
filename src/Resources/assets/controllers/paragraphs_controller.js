import {Controller} from "stimulus"

export default class extends Controller {

    static targets = ['field', 'addButton', 'type', 'fieldContainer', 'positionUp', 'positionDown']

    static values = {
        prototype: String,
        maxItems: Number,
        itemsCount: Number,
    }

    connect() {
        this.index = this.itemsCountValue = this.fieldContainerTarget.childNodes.length
        this.container = this.element.getElementsByClassName('paragraphs')[0]
        this.updatePositionValues()
    }

    addItem(event) {
        event.preventDefault()
        if (!this.hasTypeTarget) return
        const type = this.typeTarget.value
        if (!type) return

        const prototype = this.fieldContainerTarget.dataset['prototype' + this.capitalizeString(type)]

        const newField = prototype.replace(/__name__/g, this.index)
        this.fieldContainerTarget.insertAdjacentHTML('beforeend', newField)
        this.index++
        this.itemsCountValue++
        this.updatePositionValues()
    }

    remove(event) {
        event.preventDefault()

        if (confirm('Are you sure you want to delete this item?')) {
            if (this.getCurrentNode(event)) {
                this.getCurrentNode(event).remove()
                this.itemsCountValue--
                this.updatePositionValues()
            }
        }
    }

    itemsCountValueChanged() {
        console.log(this.hasAddButtonTarget, this.hasMaxItemsValue)
        if (false === this.hasAddButtonTarget || 0 === this.maxItemsValue) {
            return
        }
        const maxItemsReached = this.itemsCountValue >= this.maxItemsValue
        this.addButtonTarget.classList.toggle('hidden', maxItemsReached)
    }

    moveUp(event) {
        event.preventDefault()

        let position = 0
        let counter = 0

        this.container.childNodes.forEach(node => {
            if (node.contains(event.currentTarget)) {
                position = counter
            }

            counter++
        })

        const targetPosition = position - 1
        if (targetPosition >= 0) {
            this.container.insertBefore(this.container.childNodes[position], this.container.childNodes[targetPosition])
        }

        this.updatePositionValues()
    }

    moveDown(event) {
        event.preventDefault()
        const initPosition = event.pageY

        let position = 0
        let counter = 0

        const maxValue = this.container.childNodes.length

        this.container.childNodes.forEach(node => {
            if (node.contains(event.currentTarget)) {
                position = counter
            }

            counter++
        })

        const targetPosition = position + 1

        if (targetPosition < maxValue) {
            this.container.insertBefore(this.container.childNodes[position], this.container.childNodes[targetPosition].nextSibling)
        }

        this.updatePositionValues()
    }

    capitalizeString(str) {
        return str.charAt(0).toUpperCase() + str.slice(1)
    }

    updatePositionValues() {
        this.container.childNodes.forEach((node, index) => {
            const typeField = node.querySelector('[id$=_position]')
            typeField.value = index
        })
    }

    getCurrentNode(event) {
        const filteredResult = Array.prototype.filter.call(this.container.childNodes, node => {
            return node.contains(event.currentTarget)
        })

        return filteredResult.length ? filteredResult[0] : null
    }
}