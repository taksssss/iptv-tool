<template>
  <div class="icon-upload">
    <el-card>
      <template #header>
        <span>上传台标</span>
      </template>
      
      <el-form :model="form" label-width="100px">
        <el-form-item label="频道名称">
          <el-input v-model="form.channelName" placeholder="请输入频道名称" />
        </el-form-item>
        
        <el-form-item label="台标图片">
          <el-upload
            class="icon-uploader"
            :auto-upload="false"
            :show-file-list="false"
            :on-change="handleChange"
          >
            <img v-if="imageUrl" :src="imageUrl" class="icon-preview" />
            <el-icon v-else class="icon-uploader-icon"><Plus /></el-icon>
          </el-upload>
          <div class="upload-tip">建议上传 PNG 格式，尺寸 200x200 像素</div>
        </el-form-item>
        
        <el-form-item>
          <el-button type="primary" @click="handleUpload" :loading="uploading">
            上传台标
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { iconApi } from '@/api/icon'

const form = ref({
  channelName: ''
})
const imageUrl = ref('')
const selectedFile = ref(null)
const uploading = ref(false)

const handleChange = (file) => {
  selectedFile.value = file.raw
  imageUrl.value = URL.createObjectURL(file.raw)
}

const handleUpload = async () => {
  if (!form.value.channelName) {
    ElMessage.error('请输入频道名称')
    return
  }
  if (!selectedFile.value) {
    ElMessage.error('请选择台标图片')
    return
  }

  uploading.value = true
  try {
    await iconApi.uploadIcon(selectedFile.value, form.value.channelName)
    ElMessage.success('上传成功')
    form.value.channelName = ''
    imageUrl.value = ''
    selectedFile.value = null
  } catch (error) {
    ElMessage.error('上传失败')
  } finally {
    uploading.value = false
  }
}
</script>

<style scoped>
.icon-upload {
  padding: 20px;
}

.icon-uploader :deep(.el-upload) {
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: all 0.3s;
}

.icon-uploader :deep(.el-upload:hover) {
  border-color: #409eff;
}

.icon-uploader-icon {
  font-size: 28px;
  color: #8c939d;
  width: 178px;
  height: 178px;
  text-align: center;
  line-height: 178px;
}

.icon-preview {
  width: 178px;
  height: 178px;
  display: block;
}

.upload-tip {
  margin-top: 10px;
  font-size: 12px;
  color: #909399;
}
</style>
