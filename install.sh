#!/bin/bash
#
# @file install.sh
# @brief IPTV工具箱管理脚本
#
# 该脚本提供IPTV工具箱的安装、管理、更新和信息查看功能，
# 包含菜单交互界面和常用操作的快捷执行。
#
# 作者: Tak
# GitHub: https://github.com/taksssss/iptv-tool
#

CONTAINER_NAME="php-epg"
IMAGE_NAME="taksss/php-epg:latest"
TENCENT_IMAGE="ccr.ccs.tencentyun.com/taksss/php-epg:latest"
DATA_DIR="$HOME/epg"
UPDATE_CONTAINER="php-epg-update"

# HTTPS 相关
ENABLE_HTTPS=false
FORCE_HTTPS=false
CERT_DST_PATH="/etc/ssl/certs/server.crt"
KEY_DST_PATH="/etc/ssl/private/server.key"

# 颜色定义
RED="\033[31m"
GREEN="\033[32m"
YELLOW="\033[33m"
BLUE="\033[34m"
CYAN="\033[36m"
BOLD="\033[1m"
RESET="\033[0m"

# 检测 sudo
SUDO_CMD=""
if [ "$(id -u)" -ne 0 ]; then
    SUDO_CMD="sudo"
fi

docker_cmd() {
    if docker info >/dev/null 2>&1; then
        docker "$@"
    else
        $SUDO_CMD docker "$@"
    fi
}

install_docker() {
    if ! command -v docker >/dev/null 2>&1; then
        echo -e "${YELLOW}未检测到 Docker，开始安装...${RESET}"
        curl -fsSL https://get.docker.com | $SUDO_CMD bash -s docker --mirror Aliyun
        $SUDO_CMD systemctl enable docker
        $SUDO_CMD systemctl start docker
        echo -e "${GREEN}Docker 安装完成${RESET}"
    fi
}

show_main_menu() {
    clear
    echo -e "${CYAN}================================================${RESET}"
    echo -e "                   ${BOLD}${GREEN}IPTV工具箱${RESET}"
    echo -e "${CYAN}================================================${RESET}"
    echo -e "${BOLD}${YELLOW}项目地址：${RESET}${GREEN}https://github.com/taksssss/iptv-tool${RESET}"
    echo -e "${CYAN}================================================${RESET}"
    echo -e " ${GREEN}1)${RESET} 安装部署"
    echo -e " ${GREEN}2)${RESET} 管理容器"
    echo -e " ${GREEN}3)${RESET} 更新容器"
    echo -e " ${GREEN}4)${RESET} 容器信息"
    echo ""
    echo -e " ${GREEN}0)${BOLD}${RED} 打赏项目${RESET}"
    echo -e " ${GREEN}q)${RESET} 退出"
    echo -e "${CYAN}================================================${RESET}"
    echo -ne "${BOLD}请选择: ${RESET}"
}

show_manage_menu() {
    clear
    echo -e "${CYAN}================ 管理容器 ===============${RESET}"
    echo -e " ${GREEN}1)${RESET} 停止容器"
    echo -e " ${GREEN}2)${RESET} 重启容器"
    echo -e " ${GREEN}3)${RESET} 卸载容器"
    echo -e " ${GREEN}0)${RESET} 返回上级"
    echo -e "${CYAN}=========================================${RESET}"
    echo -ne "${BOLD}请选择: ${RESET}"
}

show_update_menu() {
    clear
    echo -e "${CYAN}================ 更新容器 ===============${RESET}"
    echo -e " ${GREEN}1)${RESET} 手动更新"
    echo -e " ${GREEN}2)${RESET} 自动更新"
    echo -e " ${GREEN}0)${RESET} 返回上级"
    echo -e "${CYAN}=========================================${RESET}"
    echo -ne "${BOLD}请选择: ${RESET}"
}

show_info_menu() {
    clear
    echo -e "${CYAN}================ 容器信息 ===============${RESET}"
    echo -e " ${GREEN}1)${RESET} 查看状态"
    echo -e " ${GREEN}2)${RESET} 查看日志"
    echo -e " ${GREEN}0)${RESET} 返回上级"
    echo -e "${CYAN}=========================================${RESET}"
    echo -ne "${BOLD}请选择: ${RESET}"
}

donate_info() {
    clear
    echo -e "${CYAN}=============================${RESET}"
    echo -e " ${BOLD}${YELLOW}感谢您的支持${RESET}"
    echo -e "${CYAN}=============================${RESET}"

    echo -e "\n${YELLOW}微信扫码：${RESET}\n"
    echo H4sIAAAAAAAAA71UQRKEMAi77yt4ag4ceME+0JfsaFsIWOt4WGc6jlYaAgndvra9uD5vJqv5ZPsqL2l7HqDSIux4YnnuMoOJJNyGhIDZf8NjSvQUkXJbj0baOBYSzuzclQo6W4Fg9VvO+OnkTff3drQPBFLvdmvHkMLlgKz4owdoZtylgqcHqzv6zuEL5tLYOgQKPpKRxm57UbmrQCkLt8nNNoxjEm2UkvJ6ytjAwSpkYPOo1zKyR+k3DgIxYUPARS3doxedNnKep7jWJBiCvYpUeiexwJ/POUrXmX2aCS7t0f0wamBpzClo6Lzmfr4neOTK01gWPvzwlrjerCrNRm3p2n+v1/P9AFfd7fZsBwAA | base64 -d | gzip -d

    echo -e "\n${YELLOW}支付宝扫码：${RESET}\n"
    echo H4sIAAAAAAAAA71U2w3DMAj87xQ3Kh98MEEHzCRV7QBHYjtKpUZCedjAAQdsb9selNeTYEc8bG9lQTuWfmN+3I/Un01rbDnBMADhU1y+/9JupDvQgfbMI2G734N7B4GHPrEb86Aj6ecCr4RRVYx9zqwnWMj8o5rhWED5YYc/h7BgGFmGjM/CCsQGcyystKhTtMeiZtlZ6ZxFDzFNOclg9fTiL61BCdGyzKiUqfUlGLvmEU1GT5qhZfcmxdGG0hnQWhGNCyPPV3lQxyUJBQUxwjuo5kzHZN7aG1mPmhxYI23lqkq5B6IvhT608qCchA/JjQ0Caq0k3fYBVCDT+GVr8A7Qkf7Qj63x/i6P430ACGdl934HAAA= | base64 -d | gzip -d

    echo ""
    echo -e "${CYAN}=============================${RESET}"
}

choose_image() {
    echo ""
    echo "请选择镜像源:"
    echo "1) Docker Hub (默认)"
    echo "2) 腾讯云镜像"
    read -p "请输入 (1-2): " img_choice
     
    if [ "$img_choice" = "2" ]; then
        IMAGE_NAME=$TENCENT_IMAGE
    fi
}

set_env() {
    echo ""
    read -p "数据目录 [默认 $HOME/epg]: " input_dir
    [ -n "$input_dir" ] && DATA_DIR=$input_dir

    read -p "PHP 内存限制 [默认 512M]: " PHP_MEMORY_LIMIT
    PHP_MEMORY_LIMIT=${PHP_MEMORY_LIMIT:-512M}

    read -p "是否启用 HTTPS？(y/n 默认 n): " HTTPS_CHOICE
    if [[ "$HTTPS_CHOICE" =~ ^[yY]$ ]]; then
        ENABLE_HTTPS=true

        read -p "是否强制跳转到 HTTPS? (y/n 默认 y): " FORCE_CHOICE
        FORCE_CHOICE=${FORCE_CHOICE:-y}
        [[ "$FORCE_CHOICE" =~ ^[yY]$ ]] && FORCE_HTTPS=true

        echo -e "\n${YELLOW}请输入证书路径用于挂载：${RESET}"
        read -p "server.crt 文件绝对路径: " CERT_SRC_PATH
        read -p "server.key 文件绝对路径: " KEY_SRC_PATH

        if [ ! -f "$CERT_SRC_PATH" ] || [ ! -f "$KEY_SRC_PATH" ]; then
            echo -e "${RED}错误：证书文件不存在！部署已取消。${RESET}"
            exit 1
        fi

        echo -e "${GREEN}HTTPS 已启用，将自动映射证书 ✅${RESET}\n"
    fi
}

start_container() {
    echo ""
    echo "请选择运行模式:"
    echo "1) Bridge 模式 (默认)"
    echo "2) Host 模式 (支持IPv6)"
    read -p "请输入 (1-2): " mode
    mode=${mode:-1}

    choose_image
    set_env

    docker_cmd rm -f $CONTAINER_NAME >/dev/null 2>&1
    mkdir -p $DATA_DIR

    echo ""
    read -p "HTTP端口 [默认5678]: " HTTP_PORT
    HTTP_PORT=${HTTP_PORT:-5678}

    CERT_MOUNT=""
    if [ "$ENABLE_HTTPS" = "true" ]; then
        read -p "HTTPS端口 [默认5679]: " HTTPS_PORT
        HTTPS_PORT=${HTTPS_PORT:-5679}
        CERT_MOUNT="-v $CERT_SRC_PATH:$CERT_DST_PATH -v $KEY_SRC_PATH:$KEY_DST_PATH"
    fi

    if [ "$mode" = "1" ]; then
        PORT_ARGS="-p $HTTP_PORT:80"
        [ -n "$HTTPS_PORT" ] && PORT_ARGS="$PORT_ARGS -p $HTTPS_PORT:443"

        docker_cmd run -d --name $CONTAINER_NAME \
            $PORT_ARGS \
            -v $DATA_DIR:/htdocs/data \
            $CERT_MOUNT \
            -e PHP_MEMORY_LIMIT=$PHP_MEMORY_LIMIT \
            -e ENABLE_HTTPS=$ENABLE_HTTPS \
            -e FORCE_HTTPS=$FORCE_HTTPS \
            --restart unless-stopped \
            $IMAGE_NAME

    else
        echo ""
        read -p "是否启用 IPv6？(y/n 默认 y): " IPV6_CHOICE
        IPV6_CHOICE=${IPV6_CHOICE:-y}
        if [[ "$IPV6_CHOICE" =~ ^[yY]$ ]]; then
            ENABLE_IPV6=true
        else
            ENABLE_IPV6=false
        fi

        ENV_PORTS="-e HTTP_PORT=$HTTP_PORT"
        [ -n "$HTTPS_PORT" ] && ENV_PORTS="$ENV_PORTS -e HTTPS_PORT=$HTTPS_PORT"

        docker_cmd run -d --name $CONTAINER_NAME \
            --network host \
            $CERT_MOUNT \
            $ENV_PORTS \
            -e PHP_MEMORY_LIMIT=$PHP_MEMORY_LIMIT \
            -e ENABLE_HTTPS=$ENABLE_HTTPS \
            -e FORCE_HTTPS=$FORCE_HTTPS \
            -e ENABLE_IPV6=$ENABLE_IPV6 \
            -v $DATA_DIR:/htdocs/data \
            --restart unless-stopped \
            $IMAGE_NAME
    fi

    if [ $? -ne 0 ]; then
        echo -e "${RED}❌ 容器启动失败！${RESET}"
        echo "请检查："
        echo "  - 端口是否已被占用"
        echo "  - HTTPS 证书路径是否正确（如果启用了 HTTPS）"
        echo "  - 数据目录是否存在：$DATA_DIR"
        echo "  - 镜像名称是否存在：$IMAGE_NAME"
        echo "  - 是否存在同名容器：$CONTAINER_NAME"
        exit 1
    fi

    echo -e "\n${GREEN}容器已部署 ✅${RESET}"
    echo "数据目录：$DATA_DIR"
    echo "HTTP端口：$HTTP_PORT"
    [ -n "$HTTPS_PORT" ] && echo "HTTPS端口：$HTTPS_PORT"
    [ "$ENABLE_HTTPS" = "true" ] && echo "HTTPS：已启用（强制跳转：$FORCE_HTTPS）"
    echo "PHP 内存限制：$PHP_MEMORY_LIMIT"
    echo -e "\n访问地址: http://{服务器IP地址}:$HTTP_PORT/manage.php"
    echo -e "${BOLD}${RED}请务必阅读页面底部「使用说明」！${RESET}\n"
}

stop_container() {
    docker_cmd stop $CONTAINER_NAME 2>/dev/null && \
    echo -e "${GREEN}容器已停止${RESET}" || echo "${RED}容器未运行${RESET}"
}

restart_container() {
    docker_cmd restart $CONTAINER_NAME 2>/dev/null && \
    echo -e "${GREEN}容器已重启${RESET}" || echo "${RED}容器未运行${RESET}"
}

uninstall_container() {
    docker_cmd rm -f $CONTAINER_NAME 2>/dev/null && \
    echo -e "${GREEN}容器已卸载${RESET}" || echo "${RED}容器不存在${RESET}"
}

manual_update() {
    echo "触发一次更新..."
    docker_cmd rm -f $UPDATE_CONTAINER >/dev/null 2>&1
    docker_cmd run --rm --name $UPDATE_CONTAINER \
        -v /var/run/docker.sock:/var/run/docker.sock \
        containrrr/watchtower $CONTAINER_NAME --cleanup --run-once
    echo -e "${GREEN}更新完成 ✅${RESET}"
}

auto_update() {
    read -p "请输入检查更新的时间间隔(小时, 输入0关闭自动更新, 默认1): " HOURS
    HOURS=${HOURS:-1}
    docker_cmd rm -f $UPDATE_CONTAINER >/dev/null 2>&1
    if [ "$HOURS" -eq 0 ]; then
        echo -e "${YELLOW}已关闭自动更新${RESET}"
        return
    fi
    INTERVAL=$((HOURS * 3600))
    docker_cmd run -d --name $UPDATE_CONTAINER \
        -v /var/run/docker.sock:/var/run/docker.sock \
        --restart unless-stopped \
        containrrr/watchtower $CONTAINER_NAME --cleanup --interval $INTERVAL
    echo -e "${GREEN}已启动自动更新，每 $HOURS 小时检查一次 ✅${RESET}"
}

status_container() {
    if docker_cmd ps -a --format '{{.Names}}' | grep -w $CONTAINER_NAME >/dev/null; then
        docker_cmd ps -a --filter "name=$CONTAINER_NAME"
    else
        echo -e "${RED}容器不存在${RESET}"
    fi
}

show_logs() {
    docker_cmd logs -f $CONTAINER_NAME
}

install_docker

while true; do
    show_main_menu
    read choice
    case $choice in
        1) start_container ;;
        2)
            while true; do
                show_manage_menu
                read sub_choice
                case $sub_choice in
                    1) stop_container ;;
                    2) restart_container ;;
                    3) uninstall_container ;;
                    0) break ;;
                    *) echo -e "${RED}无效选择${RESET}" ;;
                esac
                [ "$sub_choice" = "0" ] || { echo "按回车键继续..."; read dummy; }
            done
            ;;
        3)
            while true; do
                show_update_menu
                read sub_choice
                case $sub_choice in
                    1) manual_update ;;
                    2) auto_update ;;
                    0) break ;;
                    *) echo -e "${RED}无效选择${RESET}" ;;
                esac
                [ "$sub_choice" = "0" ] || { echo "按回车键继续..."; read dummy; }
            done
            ;;
        4)
            while true; do
                show_info_menu
                read sub_choice
                case $sub_choice in
                    1) status_container ;;
                    2) show_logs ;;
                    0) break ;;
                    *) echo -e "${RED}无效选择${RESET}" ;;
                esac
                [ "$sub_choice" = "0" ] || { echo "按回车键继续..."; read dummy; }
            done
            ;;
        0) donate_info ;;
        q) echo "退出"; exit 0 ;;
        *) echo -e "${RED}无效选择${RESET}" ;;
    esac
    [ "$choice" = "2" ] || [ "$choice" = "3" ] || [ "$choice" = "4" ] || { echo "按回车键继续..."; read dummy; }
done
